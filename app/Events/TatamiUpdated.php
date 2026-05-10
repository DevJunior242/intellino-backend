<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TatamiUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int|string $configId) {}

    public function broadcastOn(): Channel
    {
        return new Channel("tatami.{$this->configId}");
    }

    public function broadcastAs(): string
    {
        return 'tatami.updated';
    }
}
