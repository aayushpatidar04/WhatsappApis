<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // If user is not authenticated
        if (!Auth::check()) {
            // Redirect to login page
            return redirect()->route('login')->withErrors('You must be logged in to access this page.');
        }

        // If you want extra checks (e.g. active flag)
        if (!Auth::user()->is_active) {
            Auth::logout();
            return redirect()->route('login')->withErrors('Your account is inactive.');
        }

        return $next($request);
    }
}
