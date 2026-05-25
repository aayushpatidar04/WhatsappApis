<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin\SettingsController
 *
 * Super Admin only. Session auth (web.php).
 * Manages all platform-level settings stored in platform_settings table.
 */
class SettingsController extends Controller
{
    /**
     * GET /super/settings
     * Render the settings Inertia page with all current settings grouped.
     */
    public function page(): Response
    {
        $settings = PlatformSetting::all()
            ->groupBy('group')
            ->map(fn($group) => $group->keyBy('key')
                ->map(fn($s) => [
                    'key'         => $s->key,
                    'value'       => $this->castValue($s),
                    'type'        => $s->type,
                    'label'       => $s->label,
                    'description' => $s->description,
                ])
            );

        // System info for the System tab
        $systemInfo = [
            'php_version'       => PHP_VERSION,
            'laravel_version'   => app()->version(),
            'environment'       => config('app.env'),
            'debug_mode'        => config('app.debug'),
            'queue_driver'      => config('queue.default'),
            'cache_driver'      => config('cache.default'),
            'db_connection'     => config('database.default'),
            'timezone'          => config('app.timezone'),
        ];

        return Inertia::render('Admin/Settings', [
            'settings'   => $settings,
            'systemInfo' => $systemInfo,
        ]);
    }

    /**
     * PATCH /super/settings
     * Save one or more settings at once.
     * Body: { "key1": "value1", "key2": "value2", ... }
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            '*' => ['present'],
        ]);

        // Only save keys that exist in our settings table
        $validKeys = PlatformSetting::pluck('key')->toArray();
        $toSave    = array_intersect_key($data, array_flip($validKeys));

        if (empty($toSave)) {
            return response()->json(['success' => false, 'message' => 'No valid settings keys provided.'], 422);
        }

        // Validate specific fields
        $this->validateSettings($toSave);

        // Save old values for audit
        $oldValues = [];
        foreach (array_keys($toSave) as $key) {
            $oldValues[$key] = PlatformSetting::get($key);
        }

        PlatformSetting::setMany($toSave);

        AuditLog::record(
            event:     'settings.updated',
            oldValues: $oldValues,
            newValues: $toSave,
        );

        // Clear settings cache
        Cache::forget(PlatformSetting::CACHE_KEY);

        return response()->json([
            'success' => true,
            'message' => count($toSave) . ' setting(s) saved.',
            'saved'   => array_keys($toSave),
        ]);
    }

    /**
     * POST /super/settings/cache/clear
     * Flush all Laravel caches.
     */
    public function clearCache(): JsonResponse
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Cache::forget(PlatformSetting::CACHE_KEY);

        AuditLog::record('cache.cleared');

        return response()->json([
            'success' => true,
            'message' => 'All caches cleared successfully.',
        ]);
    }

    /**
     * POST /super/settings/cache/rebuild
     * Rebuild config/route/view caches for production.
     */
    public function rebuildCache(): JsonResponse
    {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        AuditLog::record('cache.rebuilt');

        return response()->json([
            'success' => true,
            'message' => 'Caches rebuilt for production.',
        ]);
    }

    /**
     * GET /super/settings/system-health
     * Returns real-time system health data for the System tab.
     */
    public function systemHealth(): JsonResponse
    {
        $queueDepth  = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $failedJobs  = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        $activeInst  = \App\Models\WhatsappInstance::where('status', 'active')->count();
        $totalUsers  = \App\Models\User::count();
        $totalClients= \App\Models\Client::count();

        // Baileys health
        $baileys = ['online' => false, 'sessions' => 0];
        try {
            $res = app(\App\Services\BaileysClient::class)->health();
            $baileys = $res;
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'data' => [
                'queue_depth'     => $queueDepth,
                'failed_jobs'     => $failedJobs,
                'active_instances'=> $activeInst,
                'total_users'     => $totalUsers,
                'total_clients'   => $totalClients,
                'baileys'         => $baileys,
                'memory_usage'    => round(memory_get_usage(true) / 1024 / 1024, 1) . ' MB',
                'disk_free'       => round(disk_free_space('/') / 1024 / 1024 / 1024, 2) . ' GB',
            ],
        ]);
    }

    /**
     * POST /super/settings/test-email
     * Send a test email to the Super Admin.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from your InTouch Gateway. If you received this, mail is configured correctly.',
                fn($m) => $m->to($request->email)->subject('[WAP] Test Email')
            );
            return response()->json(['success' => true, 'message' => "Test email sent to {$request->email}"]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function castValue(PlatformSetting $s): mixed
    {
        return match ($s->type) {
            'boolean' => (bool) filter_var($s->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $s->value,
            'json'    => json_decode($s->value, true),
            default   => $s->value,
        };
    }

    private function validateSettings(array $settings): void
    {
        $rules = [
            'default_rate_per_minute' => fn($v) => is_numeric($v) && $v >= 5 && $v <= 200,
            'min_rate_per_minute'     => fn($v) => is_numeric($v) && $v >= 1,
            'max_rate_per_minute'     => fn($v) => is_numeric($v) && $v >= 5,
            'payment_gateway'         => fn($v) => in_array($v, ['razorpay', 'stripe', 'manual']),
            'currency'                => fn($v) => in_array(strtoupper($v), ['INR', 'USD', 'EUR', 'GBP', 'AED']),
            'session_lifetime_minutes'=> fn($v) => is_numeric($v) && $v >= 15 && $v <= 43200,
        ];

        foreach ($rules as $key => $validator) {
            if (isset($settings[$key]) && !$validator($settings[$key])) {
                abort(422, "Invalid value for setting: {$key}");
            }
        }
    }
}