<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WebhookRetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;  // retry logic handled inside WebhookService
    public int $timeout = 30;

    public function __construct(
        private readonly int    $webhookId,
        private readonly string $event,
        private readonly array  $payload,
        private readonly ?int   $messageId,
        private readonly int    $attempt,
    ) {}

    public function handle(WebhookService $webhookService): void
    {
        $webhook = Webhook::find($this->webhookId);
        if (!$webhook || !$webhook->is_active) return;

        $message = $this->messageId ? Message::find($this->messageId) : null;

        $webhookService->send($webhook, $this->event, $this->payload, $message, $this->attempt);
    }
}