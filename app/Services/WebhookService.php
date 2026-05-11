<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    private const MAX_ATTEMPTS = 5;
    private const TIMEOUT_SECONDS = 10;

    /**
     * Deliver an event to all matching webhooks for this instance.
     * Called synchronously from InternalController for inbound events.
     * Failures are queued for retry by WebhookRetryJob.
     */
    public function deliver(WhatsappInstance $instance, string $event, array $payload, ?Message $message = null): void
    {
        $webhooks = Webhook::activeFor($instance->id, $event)
            ->where(
                fn($q) => $q
                    ->where('user_id', $instance->owner_type === 'user' ? $instance->owner_id : 0)
                    ->orWhereHas('user', fn($q2) => $q2->where('client_id', $instance->client_id))
            )
            ->get();

        foreach ($webhooks as $webhook) {
            $this->send($webhook, $event, $payload, $message, attempt: 1);
        }
    }

    /**
     * Send to a single webhook endpoint.
     */
    public function send(Webhook $webhook, string $event, array $payload, ?Message $message, int $attempt): void
    {
        $body = json_encode([
            'event' => $event,
            'payload' => $payload,
            'ts' => now()->toIso8601String(),
            'attempt' => $attempt,
        ]);

        $signature = $webhook->sign($body);
        $startMs = (int) (microtime(true) * 1000);
        $success = false;
        $httpStatus = null;
        $responseBody = null;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-WA-Event' => $event,
                'X-WA-Signature' => $signature,
                'X-WA-Attempt' => (string) $attempt,
                'User-Agent' => 'WhatsApp-API-Platform/3.0',
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($webhook->url, json_decode($body, true));

            $httpStatus = $response->status();
            $responseBody = substr($response->body(), 0, 500);
            $success = $response->successful();

        } catch (\Throwable $e) {
            $responseBody = $e->getMessage();
            Log::warning("Webhook delivery failed for webhook {$webhook->id}: {$e->getMessage()}");
        }

        $durationMs = (int) (microtime(true) * 1000) - $startMs;

        // Log the attempt
        WebhookLog::create([
            'webhook_id' => $webhook->id,
            'message_id' => $message?->id,
            'event' => $event,
            'payload' => json_decode($body, true),
            'http_status' => $httpStatus,
            'response_body' => $responseBody,
            'attempt' => $attempt,
            'success' => $success,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);

        if ($success) {
            $webhook->update([
                'failure_count' => 0,
                'last_triggered_at' => now(),
            ]);
        } else {
            $webhook->increment('failure_count');

            // Auto-disable after 20 consecutive failures
            if ($webhook->failure_count >= 20) {
                $webhook->update(['is_active' => false]);
                Log::warning("Webhook {$webhook->id} auto-disabled after 20 failures.");
                return;
            }

            // Schedule retry if under max attempts
            if ($attempt < self::MAX_ATTEMPTS) {
                $delaySeconds = $this->backoffDelay($attempt);
                \App\Jobs\WebhookRetryJob::dispatch($webhook->id, $event, $payload, $message?->id, $attempt + 1)
                    ->delay(now()->addSeconds($delaySeconds))
                    ->onQueue('webhooks');
            }
        }
    }

    /**
     * Exponential backoff: 30s, 2min, 10min, 30min
     */
    private function backoffDelay(int $attempt): int
    {
        return match ($attempt) {
            1 => 30,
            2 => 120,
            3 => 600,
            4 => 1800,
            default => 3600,
        };
    }

    /**
     * Test a webhook by sending a ping event.
     */
    public function ping(Webhook $webhook): array
    {
        $body = json_encode(['event' => 'ping', 'ts' => now()->toIso8601String()]);
        $signature = $webhook->sign($body);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-WA-Event' => 'ping',
                'X-WA-Signature' => $signature,
            ])
                ->timeout(10)
                ->post($webhook->url, json_decode($body, true));

            return [
                'success' => $response->successful(),
                'http_status' => $response->status(),
                'response' => substr($response->body(), 0, 200),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}