<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects /api/internal/* endpoints.
 * Only the Baileys Node.js service (same server, localhost) can call these.
 * Validated by X-Internal-Secret header matching BAILEYS_INTERNAL_SECRET env var.
 */
class InternalSecretMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.baileys.secret', '');
        $provided = $request->header('X-Internal-Secret', '');

        if (empty($expected) || !hash_equals($expected, $provided)) {
            abort(401, 'Invalid internal secret.');
        }

        return $next($request);
    }
}