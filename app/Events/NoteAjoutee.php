<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NoteAjoutee implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int|string $configId,
        public int|string $ordrePassageId,
    ) {}
    public function broadcastOn(): Channel
    {
        return new Channel("tatami.{$this->configId}");
    }

    public function broadcastAs(): string
    {
        return 'note.ajoutee';
    }
}
