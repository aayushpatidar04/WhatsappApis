<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $primaryKey = 'key';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description'];

    // ─── Cache key ────────────────────────────────────────────────────────────

    const CACHE_KEY = 'platform_settings_all';
    const CACHE_TTL = 3600; // 1 hour

    // ─── Get a setting value (typed) ──────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();
        if (!isset($all[$key])) return $default;

        $setting = $all[$key];

        return match ($setting['type']) {
            'boolean' => filter_var($setting['value'], FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting['value'],
            'json'    => json_decode($setting['value'], true) ?? $default,
            default   => $setting['value'],
        };
    }

    // ─── Set a setting value ──────────────────────────────────────────────────

    public static function set(string $key, mixed $value): void
    {
        $store = is_array($value) ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $store]
        );

        Cache::forget(static::CACHE_KEY);
    }

    // ─── Bulk set ─────────────────────────────────────────────────────────────

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            static::set($key, $value);
        }
    }

    // ─── All settings (grouped) ───────────────────────────────────────────────

    public static function allCached(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return static::all()->keyBy('key')
                ->map(fn($s) => ['value' => $s->value, 'type' => $s->type])
                ->toArray();
        });
    }

    public static function allGrouped(): array
    {
        return static::all()
            ->groupBy('group')
            ->map(fn($items) => $items->keyBy('key'))
            ->toArray();
    }

    // ─── Default settings (used by seeder) ───────────────────────────────────

    public static function defaults(): array
    {
        return [
            // ── General ──────────────────────────────────────────────────────
            ['key' => 'app_name',               'value' => 'WhatsApp API Platform', 'type' => 'string',  'group' => 'general',  'label' => 'Platform Name',           'description' => 'Shown in emails, dashboard header, and browser tab.'],
            ['key' => 'support_email',           'value' => 'support@waplatform.com','type' => 'string',  'group' => 'general',  'label' => 'Support Email',           'description' => 'Clients contact this address for billing/support issues.'],
            ['key' => 'timezone',                'value' => 'Asia/Kolkata',           'type' => 'string',  'group' => 'general',  'label' => 'Platform Timezone',       'description' => 'Default timezone for reports, expiry checks, and scheduling.'],
            ['key' => 'maintenance_mode',        'value' => '0',                      'type' => 'boolean', 'group' => 'general',  'label' => 'Maintenance Mode',        'description' => 'Show maintenance page to all non-super-admin users.'],

            // ── Limits ───────────────────────────────────────────────────────
            ['key' => 'default_rate_per_minute', 'value' => '20',  'type' => 'integer', 'group' => 'limits', 'label' => 'Default Messages/Min',    'description' => 'Global default rate limit applied to all new clients.'],
            ['key' => 'min_rate_per_minute',     'value' => '5',   'type' => 'integer', 'group' => 'limits', 'label' => 'Minimum Messages/Min',    'description' => 'Lowest rate any client or instance can be set to.'],
            ['key' => 'max_rate_per_minute',     'value' => '60',  'type' => 'integer', 'group' => 'limits', 'label' => 'Maximum Messages/Min',    'description' => 'Highest rate Super Admin can grant to a client.'],
            ['key' => 'max_instances_per_user',  'value' => '5',   'type' => 'integer', 'group' => 'limits', 'label' => 'Max Instances / User',    'description' => 'Default max WhatsApp instances a single user can create.'],
            ['key' => 'max_bulk_recipients',     'value' => '1000','type' => 'integer', 'group' => 'limits', 'label' => 'Max Bulk Recipients',     'description' => 'Maximum recipients allowed in a single bulk send request.'],

            // ── Instance ─────────────────────────────────────────────────────
            ['key' => 'instance_grace_days',     'value' => '7',   'type' => 'integer', 'group' => 'instance', 'label' => 'Grace Period (Days)',   'description' => 'Days after expiry before session data is permanently purged.'],
            ['key' => 'expiry_warn_days_first',  'value' => '7',   'type' => 'integer', 'group' => 'instance', 'label' => 'First Expiry Warning',  'description' => 'Days before expiry to send first warning notification.'],
            ['key' => 'expiry_warn_days_second', 'value' => '3',   'type' => 'integer', 'group' => 'instance', 'label' => 'Second Expiry Warning', 'description' => 'Days before expiry to send urgent warning notification.'],
            ['key' => 'max_reconnect_attempts',  'value' => '5',   'type' => 'integer', 'group' => 'instance', 'label' => 'Max Reconnect Attempts','description' => 'Max auto-reconnect attempts before marking instance as disconnected.'],

            // ── Payment ───────────────────────────────────────────────────────
            ['key' => 'payment_gateway',         'value' => 'razorpay', 'type' => 'string',  'group' => 'payment', 'label' => 'Payment Gateway',      'description' => 'Active payment gateway for credit purchases. (razorpay | stripe)'],
            ['key' => 'currency',                'value' => 'INR',      'type' => 'string',  'group' => 'payment', 'label' => 'Default Currency',     'description' => 'Currency used for all credit package pricing.'],
            ['key' => 'enable_billing',          'value' => '1',        'type' => 'boolean', 'group' => 'payment', 'label' => 'Enable Self-Service Billing', 'description' => 'Allow client admins to purchase credits directly.'],

            // ── Notifications ─────────────────────────────────────────────────
            ['key' => 'notify_expiry_email',     'value' => '1', 'type' => 'boolean', 'group' => 'notifications', 'label' => 'Email Expiry Warnings',   'description' => 'Send email when an instance is about to expire.'],
            ['key' => 'notify_credit_low',       'value' => '1', 'type' => 'boolean', 'group' => 'notifications', 'label' => 'Credit Low Alerts',       'description' => 'Alert client admin when wallet balance drops to 0.'],
            ['key' => 'notify_campaign_done',    'value' => '1', 'type' => 'boolean', 'group' => 'notifications', 'label' => 'Campaign Complete Email',  'description' => 'Send summary email when a campaign finishes.'],
            ['key' => 'admin_alert_email',       'value' => '', 'type' => 'string',  'group' => 'notifications', 'label' => 'Admin Alert Email',       'description' => 'Email address for critical system alerts (queue depth, Baileys down).'],

            // ── Security ──────────────────────────────────────────────────────
            ['key' => 'session_lifetime_minutes','value' => '120', 'type' => 'integer', 'group' => 'security', 'label' => 'Session Lifetime (min)',   'description' => 'Web session timeout in minutes.'],
            ['key' => 'api_rate_limit_per_min',  'value' => '120', 'type' => 'integer', 'group' => 'security', 'label' => 'API Requests/Min',         'description' => 'HTTP-level rate limit on the external API (per token).'],
            ['key' => 'require_https',           'value' => '1',   'type' => 'boolean', 'group' => 'security', 'label' => 'Enforce HTTPS',            'description' => 'Redirect HTTP to HTTPS in production.'],
        ];
    }
}