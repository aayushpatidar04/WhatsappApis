<?php

namespace App\Console\Commands;

use App\Events\InstanceEvent as InstanceEventBroadcast;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs every 2 minutes via Laravel scheduler.
 * Pings Baileys for each "active" or "disconnected" instance.
 * Marks stale instances as disconnected if Baileys reports no session.
 */
class SessionHeartbeatCommand extends Command
{
    protected $signature   = 'instances:heartbeat';
    protected $description = 'Check live status of all active WhatsApp instances against Baileys.';

    public function handle(BaileysClient $baileys): int
    {
        $this->info('Running session heartbeat…');

        // Check Baileys service is up first
        $health = $baileys->health();
        if (!($health['online'] ?? false)) {
            $this->error('Baileys service is offline. Skipping heartbeat.');
            Log::critical('Baileys service offline during heartbeat check.');
            return self::FAILURE;
        }

        $instances = WhatsappInstance::whereIn('status', [
            WhatsappInstance::STATUS_ACTIVE,
            WhatsappInstance::STATUS_DISCONNECTED,
        ])->whereNull('deleted_at')->get();

        $this->info("Checking {$instances->count()} instances…");

        $reconnected  = 0;
        $disconnected = 0;

        foreach ($instances as $instance) {
            $live = $baileys->getStatus($instance->instance_token);
            $liveStatus = $live['status'] ?? 'unknown';

            if ($liveStatus === 'connected' && $instance->status !== WhatsappInstance::STATUS_ACTIVE) {
                // Baileys says connected but DB says otherwise — fix DB
                $instance->update([
                    'status'            => WhatsappInstance::STATUS_ACTIVE,
                    'last_connected_at' => now(),
                ]);
                $reconnected++;

            } elseif (in_array($liveStatus, ['not_initialised', 'disconnected', 'unknown'])
                && $instance->status === WhatsappInstance::STATUS_ACTIVE) {

                // Baileys says not connected but DB says active — mark disconnected
                $instance->update(['status' => WhatsappInstance::STATUS_DISCONNECTED]);
                $disconnected++;

                // Attempt auto-reconnect if still has credits
                if (!$instance->isExpired() && $instance->credits_assigned > 0) {
                    $baileys->createSession($instance->instance_token);
                    Log::info("Heartbeat: auto-reconnect triggered for instance {$instance->id}");
                }

                // Broadcast disconnect event to dashboard
                broadcast(new InstanceEventBroadcast(
                    $instance->instance_token,
                    'session.disconnected',
                    ['reason' => 'heartbeat_mismatch']
                ));
            }
        }

        $this->info("Done. Reconnected: {$reconnected} | Marked disconnected: {$disconnected}");
        Log::info("Heartbeat complete. Reconnected: {$reconnected}, Disconnected: {$disconnected}");

        return self::SUCCESS;
    }
}