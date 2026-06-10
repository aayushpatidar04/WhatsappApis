<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CreditController as AdminCreditController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Client\ReportsController;
use App\Http\Controllers\Client\SettingsController as ClientSettingsController;
use App\Http\Controllers\Client\TemplatesController;
use App\Http\Controllers\Client\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Web\CampaignController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\CreditController;
use App\Http\Controllers\Web\InstanceController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\TokenController;
use App\Http\Controllers\Web\WebhookController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\RateLimitController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| web.php — ALL web routes
|--------------------------------------------------------------------------
|
| RULE: If a browser/dashboard uses it → it belongs here.
|
| Two kinds of web routes:
|
|   1. INERTIA ROUTES  — return Inertia::render() → Vue page
|      e.g. GET /dashboard, GET /dashboard/instances
|
|   2. JSON ACTION ROUTES — return response()->json()
|      Called by Vue components via axios (session cookie auth).
|      e.g. POST /dashboard/instances, DELETE /dashboard/tokens/1
|      These live in web.php because the browser session IS the auth.
|
| api.php only contains:
|   - External gateway (Bearer token) — for developer integrations
|   - Internal Baileys callbacks (X-Internal-Secret) — service-to-service
*/

// ─── Auth ─────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// ─── Root redirect ────────────────────────────────────────────────────────────

Route::get('/', function () {
    if (!Auth::check())
        return redirect()->route('login');

    return redirect()->route(match (Auth::user()->role) {
        'super_admin' => 'super.dashboard',
        'client_admin' => 'client.dashboard',
        default => 'user.dashboard',
    });
});

// ═════════════════════════════════════════════════════════════════════════════
// END USER DASHBOARD
// All roles can access /dashboard/* (role gates applied per route where needed)
// ═════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth', 'active'])
    ->prefix('dashboard')
    ->name('user.')
    ->group(function () {

        // ── Inertia pages ─────────────────────────────────────────────────────
    
        Route::get('/', [DashboardController::class, 'userDashboard'])->name('dashboard');
        Route::get('/instances', [DashboardController::class, 'userInstances'])->name('instances');
        Route::get('/tokens', [DashboardController::class, 'userTokens'])->name('tokens');
        Route::get('/send', [MessageController::class, 'sendPage'])->name('send');
        // Route::get('/inbox', [MessageController::class, 'inboxPage'])->name('inbox');
        Route::get('/contacts', [ContactController::class, 'page'])->name('contacts');
        Route::get('/campaigns', [CampaignController::class, 'page'])->name('campaigns');
        Route::get('/reports', [ReportController::class, 'page'])->name('reports');
        Route::get('/webhooks', [WebhookController::class, 'page'])->name('webhooks');
        Route::get('/settings', fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'settings']))->name('settings');

        // Message JSON actions
        Route::prefix('messages')->name('messages.')->group(function () {
            Route::post('/', [MessageController::class, 'send'])->name('send');    // Quick send
            Route::get('/', [MessageController::class, 'index'])->name('index');  // Message log
            // Route::get('/inbox', [MessageController::class, 'inbox'])->name('inbox');  // Inbound only
            Route::get('/stats', [MessageController::class, 'stats'])->name('stats');  // Stats widget
        });

        // Webhook JSON actions
        Route::prefix('webhooks')->name('webhooks.')->group(function () {
            Route::get('/api', [WebhookController::class, 'index'])->name('index');
            Route::post('/', [WebhookController::class, 'store'])->name('store');
            Route::patch('/{id}', [WebhookController::class, 'update'])->name('update');
            Route::delete('/{id}', [WebhookController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/ping', [WebhookController::class, 'ping'])->name('ping');
            Route::get('/{id}/logs', [WebhookController::class, 'logs'])->name('logs');
        });

        // ── Contact JSON actions ──────────────────────────────────────────────────────
        Route::prefix('contacts')->name('contacts.')->group(function () {
            Route::get('/api', [ContactController::class, 'index'])->name('index');
            Route::post('/', [ContactController::class, 'store'])->name('store');
            Route::patch('/{id}', [ContactController::class, 'update'])->name('update');
            Route::delete('/{id}', [ContactController::class, 'destroy'])->name('destroy');
            Route::post('/import', [ContactController::class, 'import'])->name('import');
            Route::get('/tags', [ContactController::class, 'tags'])->name('tags');
            Route::get('/groups', [ContactController::class, 'groups'])->name('groups');
            Route::post('/groups', [ContactController::class, 'storeGroup'])->name('groups.store');
            Route::post('/groups/{id}/add', [ContactController::class, 'addToGroup'])->name('groups.add');
            Route::post('/groups/{id}/remove/{contactId}', [ContactController::class, 'removeFromGroup'])->name('groups.remove');
            Route::delete('/groups/{id}/destroy', [ContactController::class, 'deleteGroup'])->name('groups.destroy');
        });

        // ── Campaign JSON actions ─────────────────────────────────────────────────────
        Route::prefix('campaigns')->name('campaigns.')->group(function () {
            Route::get('/api', [CampaignController::class, 'index'])->name('index');
            Route::post('/', [CampaignController::class, 'store'])->name('store');
            Route::get('/{id}', [CampaignController::class, 'show'])->name('show');
            Route::patch('/{id}', [CampaignController::class, 'update'])->name('update');
            Route::post('/{id}/launch', [CampaignController::class, 'launch'])->name('launch');
            Route::post('/{id}/pause', [CampaignController::class, 'pause'])->name('pause');
            Route::post('/{id}/resume', [CampaignController::class, 'resume'])->name('resume');
            Route::post('/{id}/cancel', [CampaignController::class, 'cancel'])->name('cancel');
            Route::get('/{id}/recipients', [CampaignController::class, 'recipients'])->name('recipients');
            Route::get('/{id}/analytics', [CampaignController::class, 'analytics'])->name('analytics');
        });

        // ── Report JSON actions ───────────────────────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/overview', [ReportController::class, 'overview'])->name('overview');
            Route::get('/daily-volume', [ReportController::class, 'dailyVolume'])->name('daily-volume');
            Route::get('/by-instance', [ReportController::class, 'byInstance'])->name('by-instance');
            Route::get('/type-breakdown', [ReportController::class, 'typeBreakdown'])->name('type-breakdown');
            Route::get('/hourly-heatmap', [ReportController::class, 'hourlyHeatmap'])->name('hourly-heatmap');
            Route::get('/campaign-funnel', [ReportController::class, 'campaignFunnel'])->name('campaign-funnel');
            Route::get('/export', [ReportController::class, 'export'])->name('export');
        });
        // ── Instance JSON actions (Vue axios calls — session auth) ─────────────
        // Prefix: /dashboard/instances/*
    
        Route::prefix('instances')->name('instances.')->group(function () {
            Route::get('/api', [InstanceController::class, 'index'])->name('index');   // list (paginated JSON)
            Route::post('/', [InstanceController::class, 'store'])->name('store');   // create
            Route::get('/{id}', [InstanceController::class, 'show'])->name('show');
            Route::patch('/{id}', [InstanceController::class, 'update'])->name('update');  // rename / add_credits / webhook
            Route::delete('/{id}', [InstanceController::class, 'destroy'])->name('destroy');

            // Session management
            Route::post('/{id}/connect', [InstanceController::class, 'connect'])->name('connect');
            Route::get('/{id}/qr', [InstanceController::class, 'qr'])->name('qr');
            Route::get('/{id}/live-status', [InstanceController::class, 'liveStatus'])->name('live-status');
            Route::get('/{id}/account-info', [InstanceController::class, 'accountInfo'])->name('account-info');
            Route::post('/{id}/logout', [InstanceController::class, 'logout'])->name('logout');
            Route::get('/{id}/groups', [InstanceController::class, 'groups'])->name('groups');
        });

        // ── Token JSON actions ────────────────────────────────────────────────
        // Prefix: /dashboard/tokens/*
        // Session auth — no chicken-and-egg. User logged in → creates token for external use.
    
        Route::prefix('tokens')->name('tokens.')->group(function () {
            Route::get('/api', [TokenController::class, 'index'])->name('index');
            Route::post('/', [TokenController::class, 'store'])->name('store');
            Route::delete('/{id}', [TokenController::class, 'destroy'])->name('destroy');
        });

        // ── Credit ledger (user) ──────────────────────────────────────────────
        Route::get('/credits/ledger', [CreditController::class, 'userLedger'])->name('credits.ledger');

        // ── Baileys health check (for user dashboard monitor widget) ──────────
        Route::get('/baileys-health', [InstanceController::class, 'baileysHealth'])->name('baileys-health');
    });

// ═════════════════════════════════════════════════════════════════════════════
// CLIENT ADMIN DASHBOARD
// ═════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth', 'active', 'role:client_admin,super_admin'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {

        // ── Inertia pages ─────────────────────────────────────────────────────
    
        Route::get('/', [DashboardController::class, 'clientDashboard'])->name('dashboard');
        Route::get('/instances', [DashboardController::class, 'clientInstances'])->name('instances');
        Route::get('/users', [UserController::class, 'index'])->name('users');

        // ── User management JSON actions ──────────────────────────────────────
    
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{id}/credits', [UserController::class, 'allocateCredits'])->name('users.credits');

        // ── Instance JSON actions (client admin manages ALL tenant instances) ──
        // Client admin uses the same InstanceController; it scopes to client_id.
    
        Route::prefix('instances')->name('instances.')->group(function () {
            Route::get('/api', [InstanceController::class, 'index'])->name('index');
            Route::post('/', [InstanceController::class, 'store'])->name('store');
            Route::get('/{id}', [InstanceController::class, 'show'])->name('show');
            Route::patch('/{id}', [InstanceController::class, 'update'])->name('update');
            Route::delete('/{id}', [InstanceController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/connect', [InstanceController::class, 'connect'])->name('connect');
            Route::get('/{id}/qr', [InstanceController::class, 'qr'])->name('qr');
            Route::get('/{id}/live-status', [InstanceController::class, 'liveStatus'])->name('live-status');
            Route::post('/{id}/logout', [InstanceController::class, 'logout'])->name('logout');
            Route::get('/{id}/groups', [InstanceController::class, 'groups'])->name('groups');
        });

        // ── Client credit ledger ──────────────────────────────────────────────
        Route::get('/credits/ledger', [CreditController::class, 'clientLedger'])->name('credits.ledger');

        Route::get('/billing', [BillingController::class, 'page'])->name('billing');
        Route::post('/billing/initiate', [BillingController::class, 'initiate'])->name('billing.initiate');
        Route::post('/billing/verify/razorpay', [BillingController::class, 'verifyRazorpay'])->name('billing.verify.razorpay');
        Route::get('/billing/orders', [BillingController::class, 'orders'])->name('billing.orders');
        Route::post('/billing/stripe/webhook', [BillingController::class, 'stripeWebhook'])->name('billing.stripe.webhook');
        Route::get('/billing/ledger', [BillingController::class, 'ledger'])->name('billing.ledger');

        // ── Reports ──────────────────────────────────────────────────────────────────
        Route::get('/reports', [ReportsController::class, 'page'])->name('reports');
        Route::get('/reports/overview', [ReportsController::class, 'overview'])->name('reports.overview');
        Route::get('/reports/daily-volume', [ReportsController::class, 'dailyVolume'])->name('reports.daily-volume');
        Route::get('/reports/by-instance', [ReportsController::class, 'byInstance'])->name('reports.by-instance');
        Route::get('/reports/type-breakdown', [ReportsController::class, 'typeBreakdown'])->name('reports.type-breakdown');
        Route::get('/reports/hourly-heatmap', [ReportsController::class, 'hourlyHeatmap'])->name('reports.hourly-heatmap');
        Route::get('/reports/campaign-stats', [ReportsController::class, 'campaignStats'])->name('reports.campaign-stats');
        Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');

        // ── Templates ────────────────────────────────────────────────────────────────
        Route::get('/templates', [TemplatesController::class, 'page'])->name('templates');
        Route::post('/templates', [TemplatesController::class, 'store'])->name('templates.store');
        Route::patch('/templates/{id}', [TemplatesController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{id}', [TemplatesController::class, 'destroy'])->name('templates.destroy');
        Route::post('/templates/preview', [TemplatesController::class, 'preview'])->name('templates.preview');

        // ── Settings ──────────────────────────────────────────────────────────────────
        Route::get('/settings', [ClientSettingsController::class, 'page'])->name('settings');
        Route::patch('/settings/company', [ClientSettingsController::class, 'updateCompany'])->name('settings.company');
        Route::patch('/settings/profile', [ClientSettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::post('/settings/change-password', [ClientSettingsController::class, 'changePassword'])->name('settings.password');
        Route::get('/settings/activity', [ClientSettingsController::class, 'activity'])->name('settings.activity');
        Route::delete('/settings/sessions', [ClientSettingsController::class, 'signOutOther'])->name('settings.sessions');


        // ── Client Admin: Rate Limits (replace PlaceholderPage stub for /client/rate-limits) ─
        Route::get('/rate-limits', [RateLimitController::class, 'page'])->name('rate-limits');
        Route::put('/rate-limits/client', [RateLimitController::class, 'setClientRate'])->name('rate-limits.client');
        Route::put('/rate-limits/user/{userId}', [RateLimitController::class, 'setUserRate'])->name('rate-limits.user');
        Route::delete('/rate-limits/user/{userId}', [RateLimitController::class, 'resetUserRate'])->name('rate-limits.user.reset');
        Route::put('/rate-limits/instance/{id}', [RateLimitController::class, 'setInstanceRate'])->name('rate-limits.instance');
        Route::delete('/rate-limits/instance/{id}', [RateLimitController::class, 'resetInstanceRate'])->name('rate-limits.instance.reset');
    });

// ═════════════════════════════════════════════════════════════════════════════
// SUPER ADMIN DASHBOARD
// ═════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth', 'active', 'role:super_admin'])
    ->prefix('super')
    ->name('super.')
    ->group(function () {

        // ── Inertia pages ─────────────────────────────────────────────────────
    
        Route::get('/', [DashboardController::class, 'superDashboard'])->name('dashboard');
        Route::get('/clients', [ClientController::class, 'index'])->name('clients');
        Route::get('/settings', [SettingsController::class, 'page'])->name('settings');
        Route::get('/monitor', fn() => Inertia::render('Admin/Monitor'))->name('monitor');



        // ── Client management JSON actions ────────────────────────────────────
    
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::patch('/clients/{id}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{id}', [ClientController::class, 'destroy'])->name('clients.destroy');

        // ── Credit management JSON actions ────────────────────────────────────
    
        Route::post('/credits/adjust', [AdminCreditController::class, 'adjust'])->name('credits.adjust');
        Route::get('/credits/ledger', [AdminCreditController::class, 'ledger'])->name('credits.ledger');

        // ── Super admin can also manage any instance ──────────────────────────
    
        Route::prefix('instances')->name('instances.')->group(function () {
            Route::get('/', [InstanceController::class, 'index'])->name('index');
            Route::patch('/{id}', [InstanceController::class, 'update'])->name('update');
            Route::delete('/{id}', [InstanceController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/connect', [InstanceController::class, 'connect'])->name('connect');
            Route::post('/{id}/logout', [InstanceController::class, 'logout'])->name('logout');
            Route::get('/{id}/live-status', [InstanceController::class, 'liveStatus'])->name('live-status');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/system-health', [SettingsController::class, 'systemHealth'])->name('system-health');
            Route::patch('/', [SettingsController::class, 'update'])->name('update');
            Route::post('/test-email', [SettingsController::class, 'testEmail'])->name('test-email');
            Route::post('/cache/clear', [SettingsController::class, 'clearCache'])->name('cache.clear');
            Route::post('/cache/rebuild', [SettingsController::class, 'rebuildCache'])->name('cache.rebuild');
        });

        // ── Baileys health (super admin monitor page) ─────────────────────────
        Route::get('/baileys-health', [InstanceController::class, 'baileysHealth'])->name('baileys-health');

        Route::get('/packages', [PackageController::class, 'index'])->name('packages');
        Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
        Route::patch('/packages/{id}', [PackageController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{id}', [PackageController::class, 'destroy'])->name('packages.destroy');

        Route::get('/revenue', [RevenueController::class, 'page'])->name('revenue');
        Route::get('/revenue/overview', [RevenueController::class, 'overview'])->name('revenue.overview');
        Route::get('/revenue/by-client', [RevenueController::class, 'byClient'])->name('revenue.by-client');
        Route::get('/revenue/orders', [RevenueController::class, 'orders'])->name('revenue.orders');

        Route::get('/audit', fn() => Inertia::render('Admin/AuditLog'))->name('audit');
        Route::get('/audit/logs', [RevenueController::class, 'auditLog'])->name('audit.logs');
    });