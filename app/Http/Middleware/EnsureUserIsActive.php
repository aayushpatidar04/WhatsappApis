<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && !$user->is_active) {
            auth()->logout();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Contact your administrator.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        return $next($request);
    }
}