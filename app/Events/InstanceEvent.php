<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the instance's private Pusher channel.
 *
 * Channel name: private-instance.{instance_token}
 *
 * Frontend subscribes: Echo.private(`instance.${instanceToken}`)
 *   .listen('InstanceEvent', (e) => { ... })
 */
class InstanceEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $instanceToken,
        public readonly string $event,
        public readonly array  $payload = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("instance.{$this->instanceToken}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'InstanceEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'event'   => $this->event,
            'payload' => $this->payload,
            'ts'      => now()->toIso8601String(),
        ];
    }
}