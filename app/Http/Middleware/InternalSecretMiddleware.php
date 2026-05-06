<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects internal webhook routes called by the Baileys Node.js service.
 * Uses a shared secret header (not a user token).
 */
class InternalSecretMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->header('X-Internal-Secret');
        $expected = config('services.baileys.secret');

        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized internal request.',
            ], 401);
        }

        return $next($request);
    }
}