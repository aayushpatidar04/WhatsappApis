<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Web\TokenController
 *
 * Manages API token lifecycle from the dashboard.
 * Registered in web.php → session auth → NO Bearer token needed.
 *
 * Why session auth works here:
 *   The user is already logged in via browser session.
 *   They use THIS controller to CREATE tokens for their external apps.
 *   Those tokens are then used in api.php (GatewayController) from outside.
 *
 * There is no chicken-and-egg problem because:
 *   Dashboard uses session → creates token → external app uses token.
 */
class TokenController extends Controller
{
    /**
     * GET /dashboard/tokens  (returns JSON for Vue, Inertia page handled by DashboardController)
     */
    public function index(): JsonResponse
    {
        $tokens = Auth::user()
            ->apiTokens()
            ->active()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at'])
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
                'is_expired' => $t->isValid(),
            ]);

        return response()->json(['success' => true, 'data' => $tokens]);
    }

    /**
     * POST /dashboard/tokens
     * Create a new API token. Returns the plain token ONCE.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $expiresAt = isset($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        $result = ApiToken::generate(Auth::id(), $validated['name'], $expiresAt);

        $token = $result['token'];
        $plain = $result['plain'];

        return response()->json([
            'success' => true,
            'message' => 'Token created. Copy it now — it will not be shown again.',
            'data' => [
                'id' => $token->id,
                'name' => $token->name,
                'token' => $plain,   // shown ONCE — for external app use only
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * DELETE /dashboard/tokens/{id}
     * Revoke a token. Only owner can revoke.
     */
    public function destroy(int $id): JsonResponse
    {
        $token = ApiToken::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Token revoked.']);
    }
}