<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use App\Services\CreditService;
use App\Services\InsufficientCreditsException;
use App\Services\InstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Web\InstanceController
 *
 * Handles ALL instance operations called by Vue components in the dashboard.
 * Registered in web.php → protected by session auth middleware.
 * Returns JSON responses consumed by axios.
 *
 * No Bearer token. No api.token middleware. Session cookie = auth.
 */
class InstanceController extends Controller
{
    public function __construct(
        private readonly InstanceService $instanceService,
        private readonly BaileysClient $baileys,
        private readonly CreditService $credits,
    ) {
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = WhatsappInstance::whereNull('deleted_at')
            ->orderByDesc('created_at');

        // Scope by role
        if ($user->isSuperAdmin()) {
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->integer('client_id'));
            }
        } elseif ($user->isClientAdmin()) {
            // Client admin sees ALL instances in their tenant
            $query->where('client_id', $user->client_id);
        } else {
            // Regular user sees only their own instances
            $query->where('owner_id', $user->id)->where('owner_type', 'user');
        }

        $instances = $query
            ->paginate($request->integer('per_page', 15))
            ->through(fn($i) => $this->format($i));

        return response()->json(['success' => true, 'data' => $instances]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'credits' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'webhook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $user = Auth::user();

        try {
            $instance = $this->instanceService->create(
                owner: $user->isClientAdmin() ? $user->client : $user,
                name: $validated['name'],
                creditsToAssign: $validated['credits'] ?? 0,
                webhookUrl: $validated['webhook_url'] ?? null,
            );
        } catch (InsufficientCreditsException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Instance created.',
            'data' => $this->format($instance, detailed: true),
        ], 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $instance = $this->resolve($id);

        return response()->json([
            'success' => true,
            'data' => $this->format($instance, detailed: true),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $instance = $this->resolve($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'webhook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'add_credits' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ]);

        try {
            $instance = $this->instanceService->update(
                instance: $instance,
                data: $validated,
                actor: Auth::user(),
            );
        } catch (InsufficientCreditsException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Instance updated.',
            'data' => $this->format($instance->fresh(), detailed: true),
        ]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $instance = $this->resolve($id);

        if ($instance->isActive()) {
            $this->baileys->logout($instance->instance_token);
        }

        $this->instanceService->delete($instance, Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Instance deleted. Unused credits returned to your wallet.',
        ]);
    }

    // ── Session: Connect ──────────────────────────────────────────────────────

    public function connect(int $id): JsonResponse
    {
        $instance = $this->resolve($id);

        if (!$instance->isConnectable()) {
            return response()->json([
                'success' => false,
                'message' => "Instance cannot connect from state: {$instance->status}.",
            ], 422);
        }

        if ($instance->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Instance credits exhausted. Top up to reconnect.',
            ], 402);
        }

        $this->baileys->createSession($instance->instance_token);

        return response()->json([
            'success' => true,
            'message' => 'Session starting. QR code will arrive via Pusher.',
        ], 202);
    }

    // ── Session: QR Code ──────────────────────────────────────────────────────

    public function qr(int $id): JsonResponse
    {
        $instance = $this->resolve($id);
        $result = $this->baileys->getQrCode($instance->instance_token);

        // Fallback to cached QR (set by InternalController when Baileys fires qr.updated)
        $qr = $result['qr'] ?? cache("instance.qr.{$instance->instance_token}");

        return response()->json([
            'success' => true,
            'status' => $result['status'] ?? $instance->status,
            'qr' => $qr,
        ]);
    }

    // ── Session: Live Status ──────────────────────────────────────────────────

    public function liveStatus(int $id): JsonResponse
    {
        $instance = $this->resolve($id);
        $live = $this->baileys->getStatus($instance->instance_token);

        return response()->json([
            'success' => true,
            'db_status' => $instance->status,
            'live_status' => $live['status'] ?? 'unknown',
            'phone_number' => $instance->phone_number,
            'expires_at' => $instance->expires_at?->toIso8601String(),
            'days_left' => $instance->daysUntilExpiry(),
        ]);
    }

    // ── Session: Account Info ─────────────────────────────────────────────────

    public function accountInfo(int $id): JsonResponse
    {
        $instance = $this->resolve($id);

        if (!$instance->isActive()) {
            return response()->json(['success' => false, 'message' => 'Instance not active.'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->baileys->getAccountInfo($instance->instance_token),
        ]);
    }

    // ── Session: Logout ───────────────────────────────────────────────────────

    public function logout(int $id): JsonResponse
    {
        $instance = $this->resolve($id);
        $success = $this->baileys->logout($instance->instance_token);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed. Baileys server unreachable or error occurred.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Logout initiated. Session will clear shortly.',
        ]);
    }


    // ── Groups ────────────────────────────────────────────────────────────────

    public function groups(int $id): JsonResponse
    {
        $instance = $this->resolve($id);

        if (!$instance->isActive()) {
            return response()->json(['success' => false, 'message' => 'Instance not active.'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->baileys->getGroups($instance->instance_token),
        ]);
    }

    // ── Baileys Health ────────────────────────────────────────────────────────

    public function baileysHealth(): JsonResponse
    {
        $health = $this->baileys->health();

        return response()->json([
            'success' => $health['online'] ?? false,
            'data' => $health,
        ], ($health['online'] ?? false) ? 200 : 503);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve instance by ID and verify the logged-in user can access it.
     * Uses Auth::user() which comes from the session (web middleware).
     */
    private function resolve(int $id): WhatsappInstance
    {
        $user = Auth::user();
        $instance = WhatsappInstance::whereNull('deleted_at')->findOrFail($id);

        // Super admin: full access
        if ($user->isSuperAdmin())
            return $instance;

        // Client admin: any instance in their tenant
        if ($user->isClientAdmin() && $instance->client_id === $user->client_id)
            return $instance;

        // Regular user: only their own instances
        if ($instance->owner_type === 'user' && $instance->owner_id === $user->id)
            return $instance;

        abort(403, 'You do not have access to this instance.');
    }

    private function format(WhatsappInstance $i, bool $detailed = false): array
    {
        $user = Auth::user();

        $data = [
            'id' => $i->id,
            'name' => $i->name,
            'phone_number' => $i->phone_number,
            'instance_token' => $i->instance_token,
            'status' => $i->status,
            'owner_type' => $i->owner_type,
            'owner_id' => $i->owner_id,
            'is_own' => $i->owner_type === 'client'
                ? $i->owner_id === $user->client_id
                : $i->owner_id === $user->id,
            'credits_assigned' => $i->credits_assigned,
            'credits_remaining' => $i->creditsRemaining(),
            'days_until_expiry' => $i->daysUntilExpiry(),
            'expires_at' => $i->expires_at?->toIso8601String(),
            'activated_at' => $i->activated_at?->toIso8601String(),
            'last_connected_at' => $i->last_connected_at?->toIso8601String(),
            'created_at' => $i->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['webhook_url'] = $i->webhook_url;
            $data['credits_consumed'] = (float) $i->credits_consumed;
            $data['reconnect_attempts'] = $i->reconnect_attempts;
        }

        return $data;
    }
}