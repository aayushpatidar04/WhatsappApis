<?php

namespace App\Jobs;

use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInstanceStatuses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BaileysClient $baileys): void
    {
        // 1. Get the TRUTH from Node.js
        $actualStatuses = $baileys->getBulkStatus(); 
        
        if (empty($actualStatuses) && !$baileys->health()['online']) {
            Log::warning('Sync aborted: Baileys service is offline.');
            return;
        }

        // 2. Get all instances Laravel *thinks* should be online or pending
        // We ignore suspended/expired because Node shouldn't have them anyway
        $instances = WhatsappInstance::whereNotIn('status', ['suspended', 'expired'])->get();

        foreach ($instances as $instance) {
            $nodeData = $actualStatuses[$instance->instance_token] ?? null;

            if (!$nodeData) {
                // Scenario A: Node.js has absolutely no record of this instance in memory.
                // If Laravel thinks it is 'active', it has crashed or logged out while Laravel was offline.
                if ($instance->status == 'active' || $instance->status == 'disconnected') {
                    $instance->update([
                        'status' => 'pending', 
                        'phone_number' => null // Safety wipe since it's fully logged out
                    ]);
                    // Broadcast to Vue
                    event(new \App\Events\InstanceEvent($instance->instance_token, 'session.logged_out', []));
                }
                continue;
            }

            // Scenario B: Node.js has the instance. Let's compare statuses.
            $nodeStatus = $nodeData['status'];
            
            // Map Node.js statuses to Laravel DB statuses
            $mappedStatus = match($nodeStatus) {
                'connected' => 'active',
                'logged_out' => 'pending',
                'qr_pending', 'initialising' => 'pending',
                default => $nodeStatus // mostly 'disconnected'
            };

            // If there is a mismatch between the Truth (Node) and the CRM (DB)
            if ($instance->status !== $mappedStatus) {
                $instance->update([
                    'status' => $mappedStatus,
                    'phone_number' => $nodeData['phone_number'] ?? $instance->phone_number
                ]);
                
                // Fire the pusher event so the UI updates without requiring a refresh
                $pusherEvent = $mappedStatus == 'active' ? 'session.connected' : 'session.disconnected';
                event(new \App\Events\InstanceEvent($instance->instance_token, $pusherEvent, [
                    'phone_number' => $instance->phone_number
                ]));
            }
        }
    }
}