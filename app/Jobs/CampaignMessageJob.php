<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignService;
use App\Services\MessageService;
use App\Services\RateLimiterService;
use App\Models\WhatsappInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public int   $timeout = 60;
    public array $backoff  = [10, 60, 300];

    public function __construct(
        private readonly int    $campaignId,
        private readonly int    $recipientId,
        private readonly string $instanceToken,
        private readonly array  $payload,
    ) {}

    public function handle(
        MessageService   $messageService,
        CampaignService  $campaignService,
        RateLimiterService $rateLimiter,
    ): void {
        $campaign  = Campaign::find($this->campaignId);
        $recipient = CampaignRecipient::find($this->recipientId);

        if (!$campaign || !$recipient) return;

        // Abort if campaign was paused or cancelled while job was in queue
        if (in_array($campaign->status, [Campaign::STATUS_PAUSED, Campaign::STATUS_CANCELLED])) {
            $recipient->update(['status' => 'skipped']);
            return;
        }

        $instance = WhatsappInstance::where('instance_token', $this->instanceToken)->first();

        if (!$instance || !$instance->isSendable()) {
            $campaignService->markRecipientFailed($recipient, 'Instance not sendable.');
            return;
        }

        // ── Rate limiting ─────────────────────────────────────────────────────
        $recipientDelay = $rateLimiter->getSameRecipientDelayMs($instance, $this->payload['to'] ?? '');
        if ($recipientDelay > 0) {
            $this->release((int) ceil($recipientDelay / 1000));
            return;
        }

        $delayMs = $rateLimiter->getDelayMs($instance);
        if ($delayMs > 0) {
            $this->release((int) ceil($delayMs / 1000));
            return;
        }

        // ── Send ──────────────────────────────────────────────────────────────
        // Get the user associated with the campaign
        $user = $campaign->user;

        $message = $messageService->dispatch(
            instance:   $instance,
            user:       $user,
            payload:    $this->payload,
            campaignId: $campaign->id,
            priority:   'default',
        );

        // Send immediately (don't double-queue)
        $messageService->sendNow($message, $this->instanceToken, $this->payload);

        if (in_array($message->status, ['sent', 'delivered', 'read'])) {
            $campaignService->markRecipientSent($recipient, $message);
        } else {
            $campaignService->markRecipientFailed($recipient, $message->error_message ?? 'Send failed.');
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("CampaignMessageJob failed for recipient {$this->recipientId}: {$e->getMessage()}");

        $recipient = CampaignRecipient::find($this->recipientId);
        if ($recipient && $recipient->status === 'queued') {
            app(CampaignService::class)->markRecipientFailed($recipient, $e->getMessage());
        }
    }
}