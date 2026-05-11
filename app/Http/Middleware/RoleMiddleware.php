<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes:
 *   ->middleware('role:super_admin')
 *   ->middleware('role:client_admin,super_admin')
 *   ->middleware('role:user,client_admin,super_admin')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if (!in_array($user->role, $roles)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Required role: ' . implode(' or ', $roles),
                ], 403);
            }
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}