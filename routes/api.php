<?php

use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\InternalController;
use App\Http\Controllers\Web\BillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// ════════════════════════════════════════════════════════════════════════════
// GROUP B — External WhatsApp Gateway (Bearer token + Instance token)
// ════════════════════════════════════════════════════════════════════════════
// These are the PUBLIC API endpoints — for external developers/apps.
// Both Authorization: Bearer <api_token> AND X-Instance-Token are required
// for send endpoints.

Route::middleware(['api.token'])
    ->prefix('gateway')
    ->name('gateway.')
    ->group(function () {

        // Who am I? (useful for external apps to verify their token)
        Route::get('/me', [GatewayController::class, 'me'])->name('me');

        // Instance status (read-only, no X-Instance-Token needed)
        Route::get('/instances', [GatewayController::class, 'listInstances'])->name('instances.index');
        Route::get('/instances/{id}/status', [GatewayController::class, 'instanceStatus'])->name('instances.status');

        // Send messages — requires BOTH Bearer + X-Instance-Token
        Route::prefix('send')->name('send.')->group(function () {
            Route::post('/text', [GatewayController::class, 'sendText'])->name('text');
            Route::post('/image', [GatewayController::class, 'sendImage'])->name('image');
            Route::post('/video', [GatewayController::class, 'sendVideo'])->name('video');
            Route::post('/audio', [GatewayController::class, 'sendAudio'])->name('audio');
            Route::post('/document', [GatewayController::class, 'sendDocument'])->name('document');
            Route::post('/location', [GatewayController::class, 'sendLocation'])->name('location');
            Route::post('/poll', [GatewayController::class, 'sendPoll'])->name('poll');
            Route::post('/bulk', [GatewayController::class, 'sendBulk'])->name('bulk');
        });

        // Message logs (external read)
        Route::get('/messages', [GatewayController::class, 'messages'])->name('messages');
    });


// ════════════════════════════════════════════════════════════════════════════
// GROUP C — Internal (Baileys → Laravel callbacks — NOT user-facing)
// ════════════════════════════════════════════════════════════════════════════

Route::middleware(['internal.secret'])
    ->prefix('internal/instances/{token}')
    ->name('internal.')
    ->group(function () {
        Route::post('/event', [InternalController::class, 'handleEvent'])->name('event');
        Route::get('/session-data', [InternalController::class, 'getSessionData'])->name('session-data.get');
        Route::post('/session-data', [InternalController::class, 'setSessionData'])->name('session-data.set');
    });

// ─── Public: no auth required ─────────────────────────────────────────────────
Route::get('/ping', fn() => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// ─── Token-authenticated routes ───────────────────────────────────────────────
Route::middleware('api.token')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Phase 2+ routes — stubs defined here, implemented in later phases
    |----------------------------------------------------------------------
    */

    // QR code & session management (Phase 2)
    Route::prefix('instances/{id}')->group(function () {
        Route::get('/qr', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
        Route::get('/status', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
        Route::post('/logout', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
        Route::post('/reconnect', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    });

    // Messages log (Phase 3)
    Route::get('/messages', fn() => response()->json(['message' => 'Available in Phase 3'], 501));

    // Webhooks (Phase 3)
    Route::prefix('webhooks')->group(function () {
        Route::get('/', fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/', fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::delete('/{id}', fn() => response()->json(['message' => 'Available in Phase 3'], 501));
    });

    // Campaigns (Phase 4)
    Route::prefix('campaigns')->group(function () {
        Route::get('/', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::post('/', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::get('/{id}', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::patch('/{id}', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::delete('/{id}', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
    });

    // Contacts (Phase 4)
    Route::prefix('contacts')->group(function () {
        Route::get('/', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::post('/', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::post('/import', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
    });
});

// ─── Internal webhook receiver (from Baileys Node.js service) ─────────────────
// Secured by X-Internal-Secret header — NOT by user token
Route::prefix('internal')->middleware('internal.secret')->group(function () {
    Route::post('/message-received', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    Route::post('/message-ack', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    Route::post('/session-connected', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    Route::post('/session-dropped', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
});

Route::post('/stripe/webhook', [BillingController::class, 'stripeWebhook'])
    ->name('stripe.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);






// ── Pusher channel authorisation ──────────────────────────────────────────────
// Required for private-instance.{token} channels.
// Add this to routes/channels.php (create if not exists):
/*
Broadcast::channel('instance.{token}', function ($user, $token) {
    // Allow access if the user owns the instance or is an admin in the same tenant
    $instance = \App\Models\WhatsappInstance::where('instance_token', $token)->first();
    if (!$instance) return false;
    if ($user->isSuperAdmin()) return true;
    if ($user->isClientAdmin() && $instance->client_id === $user->client_id) return true;
    if ($instance->owner_type === 'user' && $instance->owner_id === $user->id) return true;
    return false;
});
*/


