<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use App\Services\InstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InstanceController extends Controller
{
    public function __construct(
        private readonly InstanceService $instanceService,
        private readonly BaileysClient   $baileysClient,
    ) {}

    /**
     * GET /api/instances
     * List all instances owned by the authenticated user/client.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = WhatsappInstance::with('client:id,name')
            ->whereNull('deleted_at');

        if ($user->isSuperAdmin()) {
            // Super admin: see all, optionally filter by client
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }
        } elseif ($user->isClientAdmin()) {
            // Client admin: see all instances in their tenant
            // (both their own and their users')
            $query->where('client_id', $user->client_id);
        } else {
            // Regular user: only their own instances
            $query->where('owner_id', $user->id)->where('owner_type', 'user');
        }

        $instances = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15))
            ->through(fn($i) => $this->formatInstance($i));

        return response()->json([
            'success' => true,
            'data'    => $instances,
        ]);
    }

    /**
     * POST /api/instances
     * Create a new WhatsApp instance.
     *
     * When called by a client_admin, the owner is the Client model.
     * When called by a regular user, the owner is the User model.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'credits'     => ['sometimes', 'integer', 'min:0', 'max:120'],
            'webhook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $user  = auth()->user();
        $owner = $user->isClientAdmin() ? $user->client : $user;

        if (!$owner) {
            return response()->json([
                'success' => false,
                'message' => 'Owner account not found.',
            ], 422);
        }

        $instance = $this->instanceService->create(
            owner:            $owner,
            name:             $validated['name'],
            creditsToAssign:  $validated['credits'] ?? 0,
            webhookUrl:       $validated['webhook_url'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Instance created successfully.',
            'data'    => $this->formatInstance($instance),
        ], 201);
    }

    /**
     * GET /api/instances/{id}
     * Get a single instance's details.
     */
    public function show(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatInstance($instance, detailed: true),
        ]);
    }

    /**
     * PATCH /api/instances/{id}
     * Update name, webhook_url, or add more credits.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'webhook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'add_credits' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ]);

        $instance = $this->instanceService->update(
            instance: $instance,
            data:     $validated,
            actor:    auth()->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Instance updated.',
            'data'    => $this->formatInstance($instance, detailed: true),
        ]);
    }

    /**
     * DELETE /api/instances/{id}
     * Soft-delete an instance (must not be active).
     */
    public function destroy(int $id): JsonResponse
    {
        $instance = $this->findAndAuthorize($id);

        $this->instanceService->delete($instance, auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Instance deleted. Unused credits have been returned to your wallet.',
        ]);
    }

    /**
     * GET /api/baileys-health
     * Proxy to Baileys Node.js health check endpoint.
     */
    public function baileysHealth(): JsonResponse
    {
        $health = $this->baileysClient->health();

        $statusCode = $health['online'] ? 200 : 503;

        return response()->json([
            'success' => $health['online'],
            'data'    => $health,
        ], $statusCode);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Find an instance and verify the authenticated user can access it.
     */
    private function findAndAuthorize(int $id): WhatsappInstance
    {
        $user     = auth()->user();
        $instance = WhatsappInstance::whereNull('deleted_at')->findOrFail($id);

        // Super admin: access all
        if ($user->isSuperAdmin()) {
            return $instance;
        }

        // Client admin: access all instances in their tenant
        if ($user->isClientAdmin() && $instance->client_id === $user->client_id) {
            return $instance;
        }

        // Regular user: only their own instances
        if ($instance->owner_type === 'user' && $instance->owner_id === $user->id) {
            return $instance;
        }

        abort(403, 'You do not have access to this instance.');
    }

    /**
     * Format an instance for API response.
     * The instance_token IS exposed here — it's the routing key, not a secret.
     * session_data is NEVER exposed.
     */
    private function formatInstance(WhatsappInstance $instance, bool $detailed = false): array
    {
        $data = [
            'id'             => $instance->id,
            'name'           => $instance->name,
            'phone_number'   => $instance->phone_number,
            'instance_token' => $instance->instance_token,
            'status'         => $instance->status,
            'owner_type'     => $instance->owner_type,
            'credits_assigned'=> $instance->credits_assigned,
            'credits_remaining'=> $instance->creditsRemaining(),
            'days_until_expiry'=> $instance->daysUntilExpiry(),
            'expires_at'     => $instance->expires_at?->toIso8601String(),
            'activated_at'   => $instance->activated_at?->toIso8601String(),
            'created_at'     => $instance->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['webhook_url']       = $instance->webhook_url;
            $data['credits_consumed']  = (float) $instance->credits_consumed;
            $data['reconnect_attempts']= $instance->reconnect_attempts;
            $data['last_connected_at'] = $instance->last_connected_at?->toIso8601String();
            $data['client']            = $instance->client?->only('id', 'name');
        }

        return $data;
    }
}