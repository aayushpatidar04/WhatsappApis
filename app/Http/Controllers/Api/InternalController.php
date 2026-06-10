<?php

namespace App\Http\Controllers\Api;

use App\Events\InstanceEvent as InstanceEventBroadcast;
use App\Http\Controllers\Controller;
use App\Models\InstanceAuthState;
use App\Models\InstanceEvent;
use App\Models\WhatsappInstance;
use App\Services\CreditService;
use App\Services\MessageService;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * InternalController
 *
 * Handles all callbacks from the Baileys Node.js service.
 * Protected by X-Internal-Secret header (checked in middleware).
 *
 * These endpoints are NOT part of the public API — they are internal
 * service-to-service communication only.
 */
class InternalController extends Controller
{
    public function __construct(
        private readonly CreditService $creditService,
        private readonly MessageService $messageService,
        private readonly WebhookService $webhookService,
    ) {
    }

    /**
     * POST /api/internal/instances/{token}/event
     *
     * Central event handler — Baileys POSTs all lifecycle events here.
     * Routes each event to the appropriate handler method.
     */
    public function handleEvent(Request $request, string $token): JsonResponse
    {
        $instance = WhatsappInstance::where('instance_token', $token)
            ->whereNull('deleted_at')
            ->first();

        if (!$instance) {
            Log::warning("Internal event for unknown token: {$token}");
            return response()->json(['ok' => false, 'message' => 'Instance not found.'], 404);
        }

        $event = $request->input('event');
        $payload = $request->except('event');

        // Log the event
        InstanceEvent::create([
            'instance_id' => $instance->id,
            'event' => $event,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        // Route event to handler
        match ($event) {
            'qr.updated' => $this->onQrUpdated($instance, $payload),
            'session.connected' => $this->onConnected($instance, $payload),
            'session.disconnected' => $this->onDisconnected($instance, $payload),
            'session.logged_out' => $this->onLoggedOut($instance),
            'session.max_reconnects_reached' => $this->onMaxReconnects($instance),
            'session.error' => $this->onError($instance, $payload),
            'message.inbound' => $this->onInboundMessage($instance, $payload),
            'message.ack' => $this->onMessageAck($instance, $payload),
            default => Log::info("Unhandled instance event: {$event}"),
        };

        // Broadcast to Pusher so the dashboard updates in real time
        broadcast(new InstanceEventBroadcast($token, $event, $payload));

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/internal/instances/{token}/session-data
     * Called by Baileys to restore auth state when a session restarts.
     */
    public function getSessionData(string $token): JsonResponse
    {
        $authState = InstanceAuthState::findByToken($token);

        if (!$authState) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $authState->session_data]);
    }

    /**
     * POST /api/internal/instances/{token}/session-data
     * Called by Baileys to persist new auth state after creds.update.
     */
    public function setSessionData(Request $request, string $token): JsonResponse
    {
        $instance = WhatsappInstance::where('instance_token', $token)->first();

        if (!$instance) {
            return response()->json(['ok' => false], 404);
        }

        $data = $request->input('data', []);

        InstanceAuthState::updateOrCreate(
            ['instance_token' => $token],
            [
                'instance_id' => $instance->id,
                'session_data' => $data,
                'last_synced_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    // ─── Event handlers ───────────────────────────────────────────────────────

    private function onQrUpdated(WhatsappInstance $instance, array $payload): void
    {
        // Store QR code temporarily for polling fallback
        cache()->put(
            "instance.qr.{$instance->instance_token}",
            $payload['qr'] ?? null,
            now()->addMinutes(3)
        );
    }

    private function onConnected(WhatsappInstance $instance, array $payload): void
    {
        $isFirstConnection = $instance->status === WhatsappInstance::STATUS_PENDING;

        $updateData = [
            'status' => WhatsappInstance::STATUS_ACTIVE,
            'reconnect_attempts' => 0,
            'last_connected_at' => now(),
        ];

        if ($payload['phone_number'] ?? null) {
            $updateData['phone_number'] = $payload['phone_number'];
        }

        // Set activated_at and calculate expires_at on first connection
        if ($isFirstConnection) {
            if (is_null($instance->activated_at)) {
                $updateData['activated_at'] = now();
            }

            if (is_null($instance->expires_at) && $instance->credits_assigned > 0) {
                // Use activated_at as the base instead of now()
                $updateData['expires_at'] = $updateData['activated_at']->copy()
                    ->addDays($instance->credits_assigned * 30);
            }
        }


        $instance->update($updateData);

        Log::info("Instance connected: {$instance->id} | Phone: " . ($payload['phone_number'] ?? 'unknown'));

    }

    private function onDisconnected(WhatsappInstance $instance, array $payload): void
    {
        $instance->update([
            'status' => WhatsappInstance::STATUS_DISCONNECTED,
            'reconnect_attempts' => ($instance->reconnect_attempts ?? 0) + 1,
        ]);

        Log::warning("Instance disconnected: {$instance->id} | Reason: {$payload['reason']}");
    }

    private function onLoggedOut(WhatsappInstance $instance): void
    {
        // Calculate remaining credits
        $remaining = $instance->credits_assigned;

        if ($remaining <= 0) {
            // ❌ No credits left → suspend
            $instance->update([
                'status' => WhatsappInstance::STATUS_SUSPENDED,
                'phone_number' => null,
                'suspended_at' => now(),
            ]);
            Log::info("Instance suspended (credits exhausted): {$instance->id}");
        } else {
            // ✅ Credits still available → reset to pending
            $instance->update([
                'status' => WhatsappInstance::STATUS_PENDING,
                'phone_number' => null,
                'session_data' => null,
                'last_connected_at' => null,
                'reconnect_attempts' => 0,
            ]);
            Log::info("Instance manually logged out → pending: {$instance->id}");
        }

        // Always clear auth state since session is destroyed
        InstanceAuthState::where('instance_token', $instance->instance_token)->delete();
    }



    private function onMaxReconnects(WhatsappInstance $instance): void
    {
        $instance->update(['status' => WhatsappInstance::STATUS_DISCONNECTED]);

        Log::error("Instance max reconnects reached: {$instance->id}");
    }

    private function onError(WhatsappInstance $instance, array $payload): void
    {
        Log::error("Instance session error: {$instance->id}", $payload);
    }

    private function onInboundMessage(WhatsappInstance $instance, array $payload): void
    {
        try {
            // Store in DB
            $message = $this->messageService->storeInbound($instance, $payload);

            // Deliver to registered webhooks
            $this->webhookService->deliver($instance, 'message.inbound', [
                'instance_token' => $instance->instance_token,
                'from' => $this->extractPhone($payload),
                'type' => $payload['type'] ?? 'text',
                'body' => $payload['body'],
                'wa_message_id' => $payload['wa_message_id'],
                'timestamp' => $payload['timestamp'],
            ], $message);

        } catch (\Throwable $e) {
            Log::error("Failed to process inbound message for instance {$instance->id}: {$e->getMessage()}");
        }
    }

    private function onMessageAck(WhatsappInstance $instance, array $payload): void
    {
        try {
            $this->messageService->applyAck(
                $instance->instance_token,
                $payload['wa_message_id'] ?? '',
                (int) ($payload['status'] ?? 0),
            );
        } catch (\Throwable $e) {
            Log::error("Failed to apply ACK for instance {$instance->id}: {$e->getMessage()}");
        }
    }

    private function extractPhone(array $payload): ?string
    {
        $meta = $payload['raw']['key'] ?? [];

        // Priority order
        $jid = $meta['senderPn']
            ?? $meta['participantPn']
            ?? $meta['remoteJid']
            ?? $payload['from_jid'] ?? null;

        if (!$jid) {
            return null;
        }

        // Strip suffixes
        $digits = str_replace(
            ['@s.whatsapp.net', '@lid', '@g.us'],
            '',
            $jid
        );

        // Normalize: 12‑digit starting with 91 (India) or plain 10‑digit
        if (preg_match('/^91\d{10}$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^\d{10}$/', $digits)) {
            return $digits;
        }

        return $digits; // fallback
    }

}