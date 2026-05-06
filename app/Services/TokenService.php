<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TokenService
{
    /**
     * Create a new API token for a user.
     * Returns the plain token (shown only once) alongside the model.
     */
    public function create(User $user, string $name, ?\Carbon\Carbon $expiresAt = null): array
    {
        // Max 10 active tokens per user
        $activeCount = ApiToken::where('user_id', $user->id)->active()->count();

        if ($activeCount >= 10) {
            throw ValidationException::withMessages([
                'name' => 'Maximum of 10 active API tokens per user. Please revoke an existing token first.',
            ]);
        }

        ['token' => $token, 'plain' => $plain] = ApiToken::generate($user->id, $name, $expiresAt);

        return [
            'token' => $token,
            'plain' => $plain, // Return ONCE, never stored in plain form
        ];
    }

    /**
     * Resolve a plain API token string to a User model.
     * Updates last_used_at on success.
     */
    public function resolveUser(string $plainToken): ?User
    {
        $token = ApiToken::findByPlain($plainToken);

        if (!$token || !$token->isValid()) {
            return null;
        }

        $user = $token->user;

        if (!$user || !$user->is_active) {
            return null;
        }

        $token->markUsed();

        return $user;
    }

    /**
     * Revoke a token. Ensures the token belongs to the requesting user.
     */
    public function revoke(int $tokenId, User $user): void
    {
        $token = ApiToken::where('id', $tokenId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $token->revoke();
    }

    /**
     * List all active tokens for a user (hashes never exposed).
     */
    public function listForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return ApiToken::where('user_id', $user->id)
            ->active()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);
    }
}