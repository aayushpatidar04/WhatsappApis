<?php

namespace App\Services;

use App\Models\MessageLimit;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Token-bucket rate limiter for outbound messages.
 *
 * Each instance has a bucket of N tokens (= max_per_minute).
 * One token is consumed per message sent.
 * Bucket refills every 60 seconds.
 * Delay between sends = 60 / max_per_minute seconds (evenly spaced).
 * Jitter of ±500ms added to avoid WhatsApp pattern detection.
 */
class RateLimiterService
{
    private const DEFAULT_RATE = 20;   // messages per minute
    private const MIN_RATE = 5;
    private const MAX_RATE = 60;

    /**
     * Get the configured rate limit for an instance (messages per minute).
     * Checks instance-level override → user/client-level → global default.
     */
    public function getRate(WhatsappInstance $instance): int
    {
        // Check instance-specific override
        $instanceLimit = DB::table('message_limits')
            ->where('instance_id', $instance->id)
            ->value('max_per_minute');

        if ($instanceLimit)
            return (int) $instanceLimit;

        // Check owner-level override
        $ownerLimit = DB::table('message_limits')
            ->where('owner_id', $instance->owner_id)
            ->where('owner_type', $instance->owner_type)
            ->whereNull('instance_id')
            ->value('max_per_minute');

        if ($ownerLimit)
            return (int) $ownerLimit;

        // Fall back to client's max_rate_per_minute
        if ($instance->client_id) {
            $clientRate = DB::table('clients')
                ->where('id', $instance->client_id)
                ->value('max_rate_per_minute');

            if ($clientRate)
                return (int) min($clientRate, self::MAX_RATE);
        }

        return self::DEFAULT_RATE;
    }

    /**
     * Calculate the delay in milliseconds before the next message
     * can be sent for this instance.
     * Returns 0 if sending can happen immediately.
     */
    public function getDelayMs(WhatsappInstance $instance): int
    {
        $rate = $this->getRate($instance);
        $intervalMs = (int) (60_000 / $rate);       // even spacing in ms
        $jitterMs = random_int(-500, 500);          // ±500ms jitter

        $key = "rate_last_sent:{$instance->id}";
        $lastSentAt = Cache::get($key);               // Unix timestamp in ms

        if (!$lastSentAt) {
            // No recent send — go immediately
            $this->markSent($instance);
            return 0;
        }

        $nowMs = (int) (microtime(true) * 1000);
        $elapsedMs = $nowMs - $lastSentAt;
        $requiredMs = $intervalMs + $jitterMs;

        if ($elapsedMs >= $requiredMs) {
            $this->markSent($instance);
            return 0;
        }

        return $requiredMs - $elapsedMs;
    }

    /**
     * Mark that a message was just sent for this instance.
     */
    public function markSent(WhatsappInstance $instance): void
    {
        $nowMs = (int) (microtime(true) * 1000);
        Cache::put("rate_last_sent:{$instance->id}", $nowMs, now()->addMinutes(2));
    }

    /**
     * Check if a consecutive-same-recipient guard is needed.
     * WA flags accounts sending many messages to the same number rapidly.
     * Enforces minimum 3 seconds between messages to the same JID.
     */
    public function getSameRecipientDelayMs(WhatsappInstance $instance, string $jid): int
    {
        $key = "rate_same_recipient:{$instance->id}:{$jid}";
        $lastMs = Cache::get($key);

        if (!$lastMs) {
            Cache::put($key, (int) (microtime(true) * 1000), now()->addSeconds(10));
            return 0;
        }

        $elapsedMs = (int) (microtime(true) * 1000) - $lastMs;
        $minGapMs = 3000; // 3 seconds minimum between same recipient

        Cache::put($key, (int) (microtime(true) * 1000), now()->addSeconds(10));

        return max(0, $minGapMs - $elapsedMs);
    }
}