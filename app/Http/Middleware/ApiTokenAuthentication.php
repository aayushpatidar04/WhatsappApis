<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\WhatsappInstance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests using the custom api_tokens table.
 *
 * Expects:
 *   Authorization: Bearer <user_api_token>
 *
 * Optionally (for send endpoints):
 *   X-Instance-Token: <instance_token>
 *
 * Sets request attributes:
 *   $request->user()         → authenticated User
 *   $request->instance       → WhatsappInstance (if X-Instance-Token present and valid)
 */
class ApiTokenAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $this->extractBearer($request);
        if (!$plain) {
            return response()->json([
                'success' => false,
                'message' => 'API token required. Pass Authorization: Bearer <token>.',
            ], 401);
        }
        
        // Resolve the token
        $apiToken = ApiToken::findByPlain($plain);
        

        if (!$apiToken || !$apiToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired API token.',
            ], 401);
        }

        $user = $apiToken->user;

        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User account is inactive.',
            ], 403);
        }

        // Bind user to request (compatible with Auth::user())
        auth()->setUser($user);
        $apiToken->markUsed();

        // Optional: resolve instance token
        $instanceToken = $request->header('X-Instance-Token');

        if ($instanceToken) {
            $instance = WhatsappInstance::where('instance_token', $instanceToken)
                ->whereNull('deleted_at')
                ->first();

            if (!$instance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid X-Instance-Token.',
                ], 422);
            }

            // Tenant isolation: instance must belong to the same client
            if ($instance->client_id !== $user->client_id && !$user->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instance does not belong to your account.',
                ], 403);
            }

            // Check ownership: user can only use their own instances
            // (or client_admin can use any instance in their tenant)
            if (
                !$user->isAdminOrAbove() &&
                !($instance->owner_type === 'user' && $instance->owner_id === $user->id)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not own this instance.',
                ], 403);
            }

            $request->merge(['_instance' => $instance]);
        }

        return $next($request);
    }

    private function extractBearer(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}