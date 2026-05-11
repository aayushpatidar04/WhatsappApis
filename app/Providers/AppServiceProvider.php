<?php

namespace App\Providers;

use App\Http\Middleware\ApiTokenAuthentication;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\InternalSecretMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Services\BaileysClient;
use App\Services\CreditService;
use App\Services\InstanceService;
use App\Services\TokenService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BaileysClient::class);
        $this->app->singleton(CreditService::class);
        $this->app->singleton(TokenService::class);
        $this->app->singleton(InstanceService::class, function ($app) {
            return new InstanceService($app->make(CreditService::class));
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerMiddlewareAliases();
    }

    private function configureRateLimiting(): void
    {
        // Rate limit for external gateway API (api.php routes)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limit for login attempts (web.php route)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email') . $request->ip());
        });
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];

        // Used in web.php — checks user role
        $router->aliasMiddleware('role', RoleMiddleware::class);

        // Used in web.php — checks user is_active
        $router->aliasMiddleware('active', EnsureUserIsActive::class);

        // Used in api.php (gateway group) — validates Bearer token from api_tokens table
        $router->aliasMiddleware('api.token', ApiTokenAuthentication::class);

        // Used in api.php (internal group) — validates X-Internal-Secret from Baileys service
        $router->aliasMiddleware('internal.secret', InternalSecretMiddleware::class);
    }
}