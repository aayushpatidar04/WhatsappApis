<?php

namespace App\Services;

use App\Events\InstanceEvent as InstanceEventBroadcast;
use App\Jobs\SendMessageJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    /**
     * Create a campaign draft.
     */
    public function create(User $user, array $data): Campaign
    {
        return Campaign::create([
            'client_id' => $user->client_id,
            'user_id' => $user->id,
            'instance_id' => $data['instance_id'] ?? null,
            'contact_group_id' => $data['contact_group_id'] ?? null,
            'name' => $data['name'],
            'status' => Campaign::STATUS_DRAFT,
            'message_type' => $data['message_type'],
            'message_payload' => $data['message_payload'],
            'schedule_time' => $data['schedule_time'] ?? null,
            'send_window_start' => $data['send_window_start'] ?? null,
            'send_window_end' => $data['send_window_end'] ?? null,
        ]);
    }

    /**
     * Add contacts as recipients to a campaign.
     * Accepts: contact IDs, group ID, or raw phone list.
     */
    public function addRecipients(Campaign $campaign, array $options): int
    {
        $contacts = collect();

        // From contact group
        if (!empty($options['contact_group_id'])) {
            $contacts = Contact::whereHas('groups', fn($q) => $q->where('contact_groups.id', $options['contact_group_id']))
                ->where('client_id', $campaign->client_id)
                ->where('is_blocked', false)
                ->get(['id', 'phone', 'name', 'custom_fields', 'tags']);
        }

        // From explicit contact IDs
        if (!empty($options['contact_ids'])) {
            $contacts = $contacts->merge(
                Contact::whereIn('id', $options['contact_ids'])
                    ->where('client_id', $campaign->client_id)
                    ->where('is_blocked', false)
                    ->get(['id', 'phone', 'name', 'custom_fields', 'tags'])
            );
        }

        // From raw phone numbers
        if (!empty($options['phones'])) {
            foreach ($options['phones'] as $phone) {
                $contacts->push((object) [
                    'id' => null,
                    'phone' => Contact::normalisePhone($phone),
                    'name' => null,
                    'custom_fields' => [],
                ]);
            }
        }

        // Deduplicate by phone
        $contacts = $contacts->unique('phone');
        $count = 0;

        DB::transaction(function () use ($campaign, $contacts, &$count) {
            foreach ($contacts as $contact) {
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'phone' => $contact->phone,
                    'name' => $contact->name,
                    'variables' => $this->buildVariables($campaign, $contact),
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
                $count++;
            }
            $campaign->update(['total_recipients' => $count]);
        });

        return $count;
    }

    public function syncRecipients(Campaign $campaign, array $options): int
    {
        return DB::transaction(function () use ($campaign, $options) {
            // 1. Delete all currently 'pending' recipients.
            // We ONLY delete pending to ensure we never accidentally delete 
            // records of messages that have already been sent/delivered.
            $campaign->recipients()->where('status', 'pending')->delete();

            // 2. Update the Campaign's group ID tracking 
            if (!empty($options['phones'])) {
                // If they switched to manual phones, remove the group association
                $campaign->update(['contact_group_id' => null]);
            } elseif (array_key_exists('contact_group_id', $options)) {
                // If they selected a group, update it
                $campaign->update(['contact_group_id' => $options['contact_group_id']]);
            }

            // 3. Re-add the fresh list of recipients using your existing logic
            $this->addRecipients($campaign, $options);

            // 4. Recalculate the exact total recipients
            // (Because addRecipients overwrites total_recipients with just the *new* count,
            // we need to recalculate the true total in case there are already sent messages)
            $trueTotal = $campaign->recipients()->count();
            $campaign->update(['total_recipients' => $trueTotal]);

            return $trueTotal;
        });
    }

    /**
     * Launch a campaign (move to running, dispatch all recipient jobs).
     */
    public function launch(Campaign $campaign): void
    {
        if (!in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED, Campaign::STATUS_PAUSED])) {
            throw new \RuntimeException("Campaign cannot be launched from status: {$campaign->status}");
        }

        $instance = $campaign->instance;
        if (!$instance || !$instance->isSendable()) {
            throw new \RuntimeException('Campaign instance is not ready to send.');
        }

        $campaign->update([
            'status' => Campaign::STATUS_RUNNING,
            'started_at' => $campaign->started_at ?? now(),
        ]);

        // Dispatch a job per pending recipient
        $campaign->recipients()
            ->where('status', 'pending')
            ->chunkById(100, function ($recipients) use ($campaign, $instance) {
                foreach ($recipients as $recipient) {
                    $payload = $this->buildPayload($campaign, $recipient);

                    \App\Jobs\CampaignMessageJob::dispatch(
                        $campaign->id,
                        $recipient->id,
                        $instance->instance_token,
                        $payload,
                    )->onQueue('default');

                    $recipient->update(['status' => 'queued']);
                }
            });

        broadcast(new InstanceEventBroadcast(
            $instance->instance_token,
            'campaign.launched',
            ['campaign_id' => $campaign->id, 'name' => $campaign->name]
        ));
    }

    /**
     * Pause a running campaign.
     */
    public function pause(Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::STATUS_RUNNING)
            return;
        $campaign->update(['status' => Campaign::STATUS_PAUSED]);
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::STATUS_PAUSED)
            return;
        $this->launch($campaign);
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(Campaign $campaign): void
    {
        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);
        $campaign->recipients()->where('status', 'pending')->update(['status' => 'skipped']);
    }

    /**
     * Mark a recipient as sent and update campaign counters.
     */
    public function markRecipientSent(CampaignRecipient $recipient, Message $message): void
    {
        DB::transaction(function () use ($recipient, $message) {
            $recipient->update(['status' => 'sent', 'message_id' => $message->id, 'sent_at' => now()]);
            Campaign::where('id', $recipient->campaign_id)->increment('sent_count');
            $this->checkCompletion($recipient->campaign_id);
        });
    }

    public function markRecipientFailed(CampaignRecipient $recipient, string $reason): void
    {
        DB::transaction(function () use ($recipient, $reason) {
            $recipient->update(['status' => 'failed', 'error_message' => $reason]);
            Campaign::where('id', $recipient->campaign_id)->increment('failed_count');
            $this->checkCompletion($recipient->campaign_id);
        });
    }

    public function markRecipientDelivered(CampaignRecipient $recipient, Message $message): void
    {
        DB::transaction(function () use ($recipient, $message) {
            $recipient->update([
                'status' => 'delivered',
                'message_id' => $message->id,
                'sent_at' => $recipient->sent_at ?? now(), // keep original sent_at if set
            ]);
            Campaign::where('id', $recipient->campaign_id)->increment('delivered_count');
            $this->checkCompletion($recipient->campaign_id);
        });
    }

    public function markRecipientRead(CampaignRecipient $recipient, Message $message): void
    {
        DB::transaction(function () use ($recipient, $message) {
            $recipient->update([
                'status' => 'read',
                'message_id' => $message->id,
                'sent_at' => $recipient->sent_at ?? now(),
            ]);
            Campaign::where('id', $recipient->campaign_id)->increment('read_count');
            $this->checkCompletion($recipient->campaign_id);
        });
    }


    // ── Private helpers ───────────────────────────────────────────────────────

    private function checkCompletion(int $campaignId): void
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign)
            return;

        $done = $campaign->sent_count + $campaign->failed_count;
        if ($done >= $campaign->total_recipients && $campaign->status === Campaign::STATUS_RUNNING) {
            $campaign->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            broadcast(new InstanceEventBroadcast(
                $campaign->instance->instance_token ?? '',
                'campaign.completed',
                [
                    'campaign_id' => $campaign->id,
                    'name' => $campaign->name,
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count,
                ]
            ));
        }
    }

    private function buildVariables(Campaign $campaign, $contact): array
    {
        return [
            'name' => $contact->name ?? '',
            'phone' => $contact->phone ?? '',
        ];
    }

    private function buildPayload(Campaign $campaign, CampaignRecipient $recipient): array
    {
        $payload = $campaign->message_payload;
        $vars = $recipient->variables ?? [];

        // Substitute {{name}}, {{phone}}, etc. in text/caption fields
        foreach (['message', 'caption', 'body'] as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($vars) {
                    return $vars[$m[1]] ?? $m[0];
                }, $payload[$field]);
            }
        }

        $payload['to'] = $recipient->phone;
        $payload['type'] = $campaign->message_type;

        return $payload;
    }


}