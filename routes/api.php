<?php

use App\Ai\Agents\SalesCoach;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api automatically.
|
| Authentication:
|   - Use the 'api.token' middleware for token-authenticated routes.
|   - The middleware sets auth()->user() from the bearer token.
|
| Instance routing:
|   - Pass X-Instance-Token header to route to a specific WA session.
|   - The middleware attaches $request->_instance when valid.
|
*/

// ─── Public: no auth required ─────────────────────────────────────────────────
Route::get('/ping', fn() => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// ─── Token-authenticated routes ───────────────────────────────────────────────
Route::middleware('api.token')->group(function () {

    // Auth / Profile
    Route::get('/me', [TokenController::class, 'me']);

    // API Token management
    Route::prefix('tokens')->group(function () {
        Route::get('/',        [TokenController::class, 'index']);
        Route::post('/',       [TokenController::class, 'store']);
        Route::delete('/{id}', [TokenController::class, 'destroy']);
    });

    // WhatsApp Instance management
    Route::prefix('instances')->group(function () {
        Route::get('/',        [InstanceController::class, 'index']);
        Route::post('/',       [InstanceController::class, 'store']);
        Route::get('/{id}',    [InstanceController::class, 'show']);
        Route::patch('/{id}',  [InstanceController::class, 'update']);
        Route::delete('/{id}', [InstanceController::class, 'destroy']);
    });

    // Baileys service health (accessible by any authenticated user)
    Route::get('/baileys-health', [InstanceController::class, 'baileysHealth']);

    /*
    |----------------------------------------------------------------------
    | Phase 2+ routes — stubs defined here, implemented in later phases
    |----------------------------------------------------------------------
    */

    // QR code & session management (Phase 2)
    Route::prefix('instances/{id}')->group(function () {
        Route::get('/qr',         fn() => response()->json(['message' => 'Available in Phase 2'], 501));
        Route::get('/status',     fn() => response()->json(['message' => 'Available in Phase 2'], 501));
        Route::post('/logout',    fn() => response()->json(['message' => 'Available in Phase 2'], 501));
        Route::post('/reconnect', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    });

    // Messaging (Phase 3)
    Route::prefix('send')->group(function () {
        Route::post('/text',       fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/image',      fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/video',      fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/audio',      fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/document',   fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/location',   fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/cta-button', fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/list',       fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/poll',       fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/bulk',       fn() => response()->json(['message' => 'Available in Phase 3'], 501));
    });

    // Messages log (Phase 3)
    Route::get('/messages', fn() => response()->json(['message' => 'Available in Phase 3'], 501));

    // Webhooks (Phase 3)
    Route::prefix('webhooks')->group(function () {
        Route::get('/',        fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::post('/',       fn() => response()->json(['message' => 'Available in Phase 3'], 501));
        Route::delete('/{id}', fn() => response()->json(['message' => 'Available in Phase 3'], 501));
    });

    // Campaigns (Phase 4)
    Route::prefix('campaigns')->group(function () {
        Route::get('/',        fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::post('/',       fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::get('/{id}',    fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::patch('/{id}',  fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::delete('/{id}', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
    });

    // Contacts (Phase 4)
    Route::prefix('contacts')->group(function () {
        Route::get('/',        fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::post('/',       fn() => response()->json(['message' => 'Available in Phase 4'], 501));
        Route::post('/import', fn() => response()->json(['message' => 'Available in Phase 4'], 501));
    });
});

// ─── Internal webhook receiver (from Baileys Node.js service) ─────────────────
// Secured by X-Internal-Secret header — NOT by user token
Route::prefix('internal')->middleware('internal.secret')->group(function () {
    Route::post('/message-received',  fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    Route::post('/message-ack',       fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    Route::post('/session-connected', fn() => response()->json(['message' => 'Available in Phase 2'], 501));
    Route::post('/session-dropped',   fn() => response()->json(['message' => 'Available in Phase 2'], 501));
});


Route::post('/coach', function (Request $request) {
    $response = (new SalesCoach)
        ->prompt('Analyze this sales transcript...', attachments: [
            $request->file('transcript'),
        ], model: 'gemini-2.5-flash');

    return [
        'analysis' => (string) $response,
    ];
});