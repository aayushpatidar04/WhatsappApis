<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\WhatsappInstance;
use App\Services\MessageService;
use App\Services\RateLimiterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public int   $timeout = 60;

    // Backoff in seconds between retries: 5s, 30s, 120s
    public array $backoff = [5, 30, 120];

    public function __construct(
        private readonly int    $messageId,
        private readonly string $instanceToken,
        private readonly array  $payload,
    ) {}

    public function handle(MessageService $messageService, RateLimiterService $rateLimiter): void
    {
        $message = Message::find($this->messageId);

        if (!$message) {
            Log::warning("SendMessageJob: message {$this->messageId} not found, skipping.");
            return;
        }

        // Skip if already sent (could happen on duplicate job)
        if (in_array($message->status, [Message::STATUS_SENT, Message::STATUS_DELIVERED, Message::STATUS_READ])) {
            return;
        }

        $instance = WhatsappInstance::where('instance_token', $this->instanceToken)->first();

        if (!$instance) {
            $messageService->markFailed($message, 'Instance not found.');
            return;
        }

        if (!$instance->isSendable()) {
            $messageService->markFailed($message, "Instance not sendable. Status: {$instance->status}.");
            return;
        }

        // ── Rate limiting ─────────────────────────────────────────────────────

        // Check same-recipient guard first
        $recipientDelay = $rateLimiter->getSameRecipientDelayMs($instance, $this->payload['to'] ?? '');
        if ($recipientDelay > 0) {
            Log::debug("SendMessageJob: same-recipient delay {$recipientDelay}ms for msg {$this->messageId}");
            $this->release((int) ceil($recipientDelay / 1000));
            return;
        }

        // Check per-instance rate limit
        $delayMs = $rateLimiter->getDelayMs($instance);
        if ($delayMs > 0) {
            Log::debug("SendMessageJob: rate delay {$delayMs}ms for msg {$this->messageId}");
            $this->release((int) ceil($delayMs / 1000));
            return;
        }

        // ── Send ──────────────────────────────────────────────────────────────

        $messageService->sendNow($message, $this->instanceToken, $this->payload);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendMessageJob permanently failed for msg {$this->messageId}: {$e->getMessage()}");

        $message = Message::find($this->messageId);
        if ($message && !in_array($message->status, [Message::STATUS_SENT, Message::STATUS_DELIVERED, Message::STATUS_READ])) {
            app(MessageService::class)->markFailed($message, $e->getMessage());
        }
    }
}