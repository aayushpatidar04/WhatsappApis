<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 2 additions to instance management:
 *   - Connect (init Baileys session)
 *   - Get QR code
 *   - Live status
 *   - Disconnect / Logout
 *   - Account info
 *   - Groups list
 *
 * Add these methods to the existing InstanceController.
 * They are shown here separately for clarity.
 */
class InstanceSessionController extends Controller
{
    public function __construct(private readonly BaileysClient $baileys) {}

    /**
     * POST /api/instances/{id}/connect
     * Initialise the Baileys session for this instance.
     * Triggers QR generation — QR is pushed via Pusher.
     */
    public function connect(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);

        if (!$instance->isConnectable()) {
            return response()->json([
                'success' => false,
                'message' => "Instance cannot be connected in its current state: {$instance->status}.",
            ], 422);
        }

        if ($instance->status === WhatsappInstance::STATUS_SUSPENDED) {
            return response()->json([
                'success' => false,
                'message' => 'Instance is suspended. Add credits to reactivate.',
            ], 402);
        }

        $result = $this->baileys->createSession($instance->instance_token);

        return response()->json([
            'success' => true,
            'message' => 'Session initialising. Watch the QR code via Pusher or poll /qr.',
            'data'    => $result,
        ], 202);
    }

    /**
     * GET /api/instances/{id}/qr
     * Poll for the current QR code (base64 PNG).
     * Returns null if already connected.
     */
    public function qr(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);

        // Try Baileys service first
        $result = $this->baileys->getQrCode($instance->instance_token);

        if ($result['success'] ?? false) {
            return response()->json([
                'success' => true,
                'status'  => $result['status'],
                'qr'      => $result['qr'] ?? null,
            ]);
        }

        // Fallback: check our cache (set by InternalController::onQrUpdated)
        $cached = cache()->get("instance.qr.{$instance->instance_token}");

        return response()->json([
            'success' => true,
            'status'  => $instance->status,
            'qr'      => $cached,
        ]);
    }

    /**
     * GET /api/instances/{id}/status
     * Live status from Baileys service.
     */
    public function liveStatus(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);
        $live     = $this->baileys->getStatus($instance->instance_token);

        return response()->json([
            'success'      => true,
            'db_status'    => $instance->status,
            'live_status'  => $live['status'] ?? 'unknown',
            'phone_number' => $instance->phone_number,
            'expires_at'   => $instance->expires_at?->toIso8601String(),
            'days_left'    => $instance->daysUntilExpiry(),
        ]);
    }

    /**
     * GET /api/instances/{id}/account-info
     * WhatsApp account details for connected instance.
     */
    public function accountInfo(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);

        if (!$instance->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Instance is not active.',
            ], 422);
        }

        $info = $this->baileys->getAccountInfo($instance->instance_token);

        return response()->json(['success' => true, 'data' => $info]);
    }

    /**
     * POST /api/instances/{id}/logout
     * Graceful WhatsApp logout. Instance moves back to pending.
     */
    public function logout(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);
        $this->baileys->logout($instance->instance_token);

        // DB update happens via InternalController when Baileys fires session.logged_out
        return response()->json([
            'success' => true,
            'message' => 'Logout initiated. Session will be cleared momentarily.',
        ]);
    }

    /**
     * GET /api/instances/{id}/groups
     * List WhatsApp groups for this instance.
     */
    public function groups(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);

        if (!$instance->isActive()) {
            return response()->json(['success' => false, 'message' => 'Instance not active.'], 422);
        }

        $groups = $this->baileys->getGroups($instance->instance_token);

        return response()->json(['success' => true, 'data' => $groups]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function findAndAuthorize(int $id): WhatsappInstance
    {
        $user     = auth()->user();
        $instance = WhatsappInstance::whereNull('deleted_at')->findOrFail($id);

        if ($user->isSuperAdmin()) return $instance;
        if ($user->isClientAdmin() && $instance->client_id === $user->client_id) return $instance;
        if ($instance->owner_type === 'user' && $instance->owner_id === $user->id) return $instance;

        abort(403, 'Access denied.');
    }
}