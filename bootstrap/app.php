<?php

use App\Http\Middleware\ApiTokenAuthentication;
use App\Http\Middleware\CustomAuthMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\InternalSecretMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'api.token' => ApiTokenAuthentication::class,
            'internal.secret' => InternalSecretMiddleware::class,
            'auth' => CustomAuthMiddleware::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render Inertia error pages for web requests
        $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
            if (!$request->expectsJson() && !$request->header('X-Inertia')) {
                $status = $response->getStatusCode();

                if (in_array($status, [403, 404, 500, 503])) {
                    return Inertia::render('Error', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                }
            }

            return $response;
        });
    })
    ->create();
