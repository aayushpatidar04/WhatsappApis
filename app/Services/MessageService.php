<?php

namespace App\Services;

use App\Events\InstanceEvent as InstanceEventBroadcast;
use App\Jobs\SendMessageJob;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(private readonly BaileysClient $baileys)
    {
    }

    /**
     * Dispatch a message through the queue (rate-limited).
     * Returns the Message record immediately (status = queued).
     */
    public function dispatch(
        WhatsappInstance $instance,
        User $user,
        array $payload,
        ?int $campaignId = null,
        string $priority = 'default',
    ): Message {
        $message = Message::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'client_id' => $user->client_id ?? $instance->client_id,
            'campaign_id' => $campaignId,
            'direction' => 'outbound',
            'recipient_jid' => $this->normaliseJid($payload['to']),
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['message'] ?? $payload['caption'] ?? null,
            'media_url' => $payload['media_url'] ?? null,
            'media_mime' => $payload['mimetype'] ?? null,
            'media_filename' => $payload['filename'] ?? null,
            'metadata' => $this->extractMetadata($payload),
            'status' => Message::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        // Dispatch queue job — rate limiter lives inside the job
        SendMessageJob::dispatch($message->id, $instance->instance_token, $payload)
            ->onQueue($priority);

        return $message;
    }

    /**
     * Send immediately (synchronous) — used by the queue job after rate check.
     */
    public function sendNow(Message $message, string $instanceToken, array $payload): void
    {
        $message->update(['status' => Message::STATUS_SENDING]);

        $result = $this->baileys->send($instanceToken, $payload);

        if ($result['success'] ?? false) {
            $message->update([
                'status' => Message::STATUS_SENT,
                'wa_message_id' => $result['wa_message_id'] ?? null,
                'sent_at' => now(),
            ]);

            // Broadcast sent event to dashboard
            broadcast(new InstanceEventBroadcast($instanceToken, 'message.sent', [
                'message_id' => $message->id,
                'status' => 'sent',
            ]));
        } else {
            $this->markFailed($message, $result['message'] ?? 'Send failed');
        }
    }

    /**
     * Store an inbound message received from Baileys.
     */
    public function storeInbound(WhatsappInstance $instance, array $payload): Message
    {
        // Deduplicate by wa_message_id
        $existing = Message::where('wa_message_id', $payload['wa_message_id'])
            ->where('instance_id', $instance->id)
            ->first();

        if ($existing)
            return $existing;

        $message = Message::create([
            'instance_id' => $instance->id,
            'user_id' => $this->resolveOwnerId($instance),
            'client_id' => $instance->client_id,
            'direction' => 'inbound',
            'wa_message_id' => $payload['wa_message_id'],
            'recipient_jid' => $payload['from_jid'],
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['body'],
            'metadata' => $payload['raw'] ?? null,
            'status' => Message::STATUS_DELIVERED,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        // Broadcast to inbox in real time
        broadcast(new InstanceEventBroadcast($instance->instance_token, 'message.inbound', [
            'message' => $this->formatMessage($message),
        ]));

        return $message;
    }

    /**
     * Update message status from a Baileys ACK.
     * ACK values: 1=server, 2=delivered, 3=read, 4=played
     */
    public function applyAck(string $instanceToken, string $waMessageId, int $ackStatus): void
    {
        $status = Message::ACK_MAP[$ackStatus] ?? null;
        if (!$status)
            return;

        $message = Message::where('wa_message_id', $waMessageId)
            ->whereHas('instance', fn($q) => $q->where('instance_token', $instanceToken))
            ->first();

        if (!$message)
            return;

        $update = ['status' => $status];
        if ($status === Message::STATUS_DELIVERED)
            $update['delivered_at'] = now();
        if ($status === Message::STATUS_READ)
            $update['read_at'] = now();

        $message->update($update);

        // Broadcast ACK to dashboard so status icons update live
        broadcast(new InstanceEventBroadcast($instanceToken, 'message.ack', [
            'message_id' => $message->id,
            'wa_message_id' => $waMessageId,
            'status' => $status,
        ]));
    }

    /**
     * Mark a message as failed after exhausting retries.
     */
    public function markFailed(Message $message, string $reason): void
    {
        $message->update([
            'status' => Message::STATUS_FAILED,
            'error_message' => $reason,
        ]);

        broadcast(new InstanceEventBroadcast(
            $message->instance->instance_token,
            'message.failed',
            ['message_id' => $message->id, 'reason' => $reason]
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function normaliseJid(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return str_contains($phone, '@') ? $phone : "{$digits}@s.whatsapp.net";
    }

    private function extractMetadata(array $payload): ?array
    {
        $skip = ['to', 'type', 'message', 'caption', 'media_url', 'filename', 'mimetype'];
        $meta = array_diff_key($payload, array_flip($skip));
        return !empty($meta) ? $meta : null;
    }

    private function resolveOwnerId(WhatsappInstance $instance): int
    {
        if ($instance->owner_type === 'user')
            return $instance->owner_id;
        // Client-owned instance → use the client admin's user ID
        return \App\Models\User::where('client_id', $instance->client_id)
            ->where('role', 'client_admin')
            ->value('id') ?? $instance->owner_id;
    }

    public function formatMessage(Message $m): array
    {
        return [
            'id' => $m->id,
            'instance_id' => $m->instance_id,
            'instance' => $m->instance,
            'direction' => $m->direction,
            'wa_message_id' => $m->wa_message_id,
            'recipient_jid' => $m->recipient_jid,
            'phone' => $m->phone,
            'type' => $m->type,
            'body' => $m->body,
            'media_url' => $m->media_url,
            'status' => $m->status,
            'queued_at' => $m->queued_at?->toIso8601String(),
            'sent_at' => $m->sent_at?->toIso8601String(),
            'delivered_at' => $m->delivered_at?->toIso8601String(),
            'read_at' => $m->read_at?->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }
}