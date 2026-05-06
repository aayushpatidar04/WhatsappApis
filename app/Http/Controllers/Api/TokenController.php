<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __construct(private readonly TokenService $tokenService) {}

    /**
     * GET /api/tokens
     * List active API tokens for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $tokens = $this->tokenService->listForUser(auth()->user());

        return response()->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }

    /**
     * POST /api/tokens
     * Create a new API token. Plain token returned ONCE — store it immediately.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        ['token' => $token, 'plain' => $plain] = $this->tokenService->create(
            user:      auth()->user(),
            name:      $validated['name'],
            expiresAt: isset($validated['expires_at'])
                ? \Carbon\Carbon::parse($validated['expires_at'])
                : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'API token created. Copy the token now — it will not be shown again.',
            'data'    => [
                'id'         => $token->id,
                'name'       => $token->name,
                'token'      => $plain,        // Plain token — shown ONCE
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * DELETE /api/tokens/{id}
     * Revoke (deactivate) an API token.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->tokenService->revoke($id, auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully.',
        ]);
    }

    /**
     * GET /api/me
     * Return the authenticated user's profile. Used as auth health-check.
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'role'           => $user->role,
                'client_id'      => $user->client_id,
                'credit_balance' => $user->credit_balance,
                'timezone'       => $user->timezone,
            ],
        ]);
    }
}