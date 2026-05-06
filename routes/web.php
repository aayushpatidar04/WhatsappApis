<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CreditController as AdminCreditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Client\UserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// End User Dashboard
Route::middleware(['auth', 'active', 'role:user,client_admin,super_admin'])->prefix('dashboard')->name('user.')->group(function () {
    Route::get('/',          [DashboardController::class, 'userDashboard'])->name('dashboard');
    Route::get('/instances', [DashboardController::class, 'userInstances'])->name('instances');
    Route::get('/tokens',    [DashboardController::class, 'userTokens'])->name('tokens');
    Route::get('/send',      fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'send']))->name('send');
    Route::get('/inbox',     fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'inbox']))->name('inbox');
    Route::get('/campaigns', fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'campaigns']))->name('campaigns');
    Route::get('/contacts',  fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'contacts']))->name('contacts');
    Route::get('/reports',   fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'reports']))->name('reports');
    Route::get('/webhooks',  fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'webhooks']))->name('webhooks');
    Route::get('/settings',  fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'settings']))->name('settings');
});

// Client Admin Dashboard
Route::middleware(['auth', 'active', 'role:client_admin,super_admin'])->prefix('client')->name('client.')->group(function () {
    Route::get('/',          [DashboardController::class, 'clientDashboard'])->name('dashboard');
    Route::get('/instances', [DashboardController::class, 'clientInstances'])->name('instances');
    Route::get('/users',     [UserController::class, 'index'])->name('users');
    Route::get('/credits',   fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'credits']))->name('credits');
    Route::get('/rate-limits',fn()=> Inertia::render('Shared/PlaceholderPage', ['page' => 'rate-limits']))->name('rate-limits');
    Route::get('/reports',   fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'reports']))->name('reports');
    Route::get('/templates', fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'templates']))->name('templates');
    Route::get('/settings',  fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'settings']))->name('settings');
    Route::post('/users',              [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{id}',        [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{id}/credits', [UserController::class, 'allocateCredits'])->name('users.credits');
});

// Super Admin Dashboard
Route::middleware(['auth', 'active', 'role:super_admin'])->prefix('super')->name('super.')->group(function () {
    Route::get('/',         [DashboardController::class, 'superDashboard'])->name('dashboard');
    Route::get('/clients',  [ClientController::class, 'index'])->name('clients');
    Route::get('/packages', fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'packages']))->name('packages');
    Route::get('/settings', fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'settings']))->name('settings');
    Route::get('/monitor',  fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'monitor']))->name('monitor');
    Route::get('/audit',    fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'audit']))->name('audit');
    Route::get('/revenue',  fn() => Inertia::render('Shared/PlaceholderPage', ['page' => 'revenue']))->name('revenue');
    Route::post('/clients',       [ClientController::class, 'store'])->name('clients.store');
    Route::patch('/clients/{id}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{id}',[ClientController::class, 'destroy'])->name('clients.destroy');
    Route::post('/credits/adjust',         [AdminCreditController::class, 'adjust'])->name('credits.adjust');
    Route::get('/credits/ledger',          [AdminCreditController::class, 'ledger'])->name('credits.ledger');
    Route::get('/credits/packages',        [AdminCreditController::class, 'packagesIndex'])->name('packages.index');
    Route::post('/credits/packages',       [AdminCreditController::class, 'packagesStore'])->name('packages.store');
    Route::patch('/credits/packages/{id}', [AdminCreditController::class, 'packagesUpdate'])->name('packages.update');
});

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(match (auth()->user()->role) {
            'super_admin'  => 'super.dashboard',
            'client_admin' => 'client.dashboard',
            default        => 'user.dashboard',
        });
    }
    return redirect()->route('login');
});