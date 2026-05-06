<?php

namespace App\Providers;

use App\Http\Middleware\ApiTokenAuthentication;
use App\Http\Middleware\EnsureUserIsActive;
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
        // Register services as singletons
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
        // HTTP API rate limiting (separate from message rate limiting)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').$request->ip());
        });
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('role',   RoleMiddleware::class);
        $router->aliasMiddleware('active', EnsureUserIsActive::class);
        $router->aliasMiddleware('api.token', ApiTokenAuthentication::class);
    }
}