<?php

namespace App\Http\Controllers\Api;

use App\Events\InstanceEvent as InstanceEventBroadcast;
use App\Http\Controllers\Controller;
use App\Models\InstanceAuthState;
use App\Models\InstanceEvent;
use App\Models\WhatsappInstance;
use App\Services\CreditService;
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
    public function __construct(private readonly CreditService $creditService)
    {
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
            $updateData['activated_at'] = now();
            if ($instance->credits_assigned > 0) {
                $updateData['expires_at'] = now()->addDays($instance->credits_assigned * 30);
            }
        }

        $instance->update($updateData);

        Log::info(
            "Instance connected: {$instance->id} | Phone: " . ($payload['phone_number'] ?? 'unknown')
        );

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
        $instance->update([
            'status' => WhatsappInstance::STATUS_SUSPENDED,
            'phone_number' => null,
            'suspended_at' => now(),
        ]);

        // Delete auth state since session is cleared
        InstanceAuthState::where('instance_token', $instance->instance_token)->delete();

        Log::info("Instance logged out: {$instance->id}");
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
        // Phase 3 will store and forward inbound messages to webhooks.
        // In Phase 2 we just log and broadcast.
        Log::info("Inbound message on instance {$instance->id} from {$payload['from_jid']}");
    }

    private function onMessageAck(WhatsappInstance $instance, array $payload): void
    {
        // Phase 3 will update message status in DB.
        Log::debug("ACK on instance {$instance->id}: msg={$payload['wa_message_id']} status={$payload['status']}");
    }
}