<?php

namespace App\Console\Commands;

use App\Events\InstanceEvent as InstanceEventBroadcast;
use App\Models\InstanceAuthState;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Runs daily at 09:00 via Laravel scheduler.
 * Checks for:
 *   1. Instances expiring within 7 days → warning notification
 *   2. Instances expiring within 3 days → urgent notification
 *   3. Instances already expired        → suspend + disconnect
 *   4. Instances past grace period      → purge session data
 */
class InstanceExpiryCheckCommand extends Command
{
    protected $signature   = 'instances:expiry-check';
    protected $description = 'Check instance credit expiry, send warnings, and suspend expired instances.';

    public function __construct(private readonly BaileysClient $baileys)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Running instance expiry check…');

        $this->warnExpiring(7);
        $this->warnExpiring(3);
        $this->suspendExpired();
        $this->purgeGracePeriod();

        $this->info('Expiry check complete.');
        return self::SUCCESS;
    }

    // ─── Warn expiring instances ──────────────────────────────────────────────

    private function warnExpiring(int $days): void
    {
        $instances = WhatsappInstance::where('status', WhatsappInstance::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '=', now()->addDays($days)->toDateString())
            ->whereNull('deleted_at')
            ->with(['client'])
            ->get();

        foreach ($instances as $instance) {
            // Broadcast warning to dashboard
            broadcast(new InstanceEventBroadcast(
                $instance->instance_token,
                'instance.expiring',
                [
                    'days_left'  => $days,
                    'expires_at' => $instance->expires_at->toIso8601String(),
                    'instance'   => ['id' => $instance->id, 'name' => $instance->name],
                ]
            ));

            Log::info("Expiry warning ({$days}d): instance {$instance->id} | expires {$instance->expires_at}");
            $this->line("  ⚠ {$days}-day warning: [{$instance->id}] {$instance->name}");
        }
    }

    // ─── Suspend expired instances ────────────────────────────────────────────

    private function suspendExpired(): void
    {
        $expired = WhatsappInstance::where('status', WhatsappInstance::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->get();

        foreach ($expired as $instance) {
            // Disconnect the Baileys session
            $this->baileys->logout($instance->instance_token);

            // Update DB status
            $instance->update([
                'status'       => WhatsappInstance::STATUS_SUSPENDED,
                'suspended_at' => now(),
            ]);

            // Notify dashboard
            broadcast(new InstanceEventBroadcast(
                $instance->instance_token,
                'instance.expired',
                ['instance' => ['id' => $instance->id, 'name' => $instance->name]]
            ));

            Log::warning("Instance suspended (credits expired): {$instance->id}");
            $this->warn("  ✗ Suspended: [{$instance->id}] {$instance->name}");
        }
    }

    // ─── Purge instances past grace period ───────────────────────────────────

    private function purgeGracePeriod(): void
    {
        $graceDays = (int) config('app.instance_grace_period_days', 7);

        $toPurge = WhatsappInstance::where('status', WhatsappInstance::STATUS_SUSPENDED)
            ->whereNotNull('suspended_at')
            ->where('suspended_at', '<', now()->subDays($graceDays))
            ->whereNull('deleted_at')
            ->get();

        foreach ($toPurge as $instance) {
            // Hard-delete from Baileys
            $this->baileys->deleteSession($instance->instance_token);

            // Remove persisted auth state
            InstanceAuthState::where('instance_token', $instance->instance_token)->delete();

            // Move to expired
            $instance->update(['status' => WhatsappInstance::STATUS_EXPIRED]);

            Log::info("Instance purged after grace period: {$instance->id}");
            $this->line("  🗑  Purged: [{$instance->id}] {$instance->name}");
        }
    }
}