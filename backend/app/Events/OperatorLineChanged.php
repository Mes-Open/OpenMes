<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A data-free nudge; operator pages re-fetch their authorized Inertia props. */
class OperatorLineChanged implements ShouldBroadcastNow
{
    public function __construct(public int $lineId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("operator-line.{$this->lineId}")];
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
