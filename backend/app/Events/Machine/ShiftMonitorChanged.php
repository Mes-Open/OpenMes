<?php

namespace App\Events\Machine;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A nudge that something a shift monitor draws has changed for one workstation.
 *
 * Deliberately carries no data. The monitor's payload is a derived aggregate —
 * hour rows, segments split against the counter feed, OEE, Pareto — not a row,
 * so there is nothing here a client could apply the way realtimeCollection
 * applies a CollectionChanged delta. Two consequences settle the design:
 *
 *  - recomputing the snapshot per broadcast would do that work for every
 *    workstation whether or not anyone has it open, and
 *  - sending it would put the derivation logic on the wire many times a minute.
 *
 * So the client re-fetches on the nudge instead: the work happens only while
 * someone is watching, and the derivation stays in one place.
 *
 * Channel: shift-monitor.{workstationId} — authorized in routes/channels.php.
 * Broadcast NOW (synchronous), matching CollectionChanged under QUEUE_CONNECTION=sync.
 */
class ShiftMonitorChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  int  $workstationId  the station whose shift changed
     * @param  string  $reason  what moved — 'state', 'counter' or 'downtime'.
     *                          Diagnostic only; the client re-fetches regardless.
     */
    public function __construct(
        public int $workstationId,
        public string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("shift-monitor.{$this->workstationId}")];
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    public function broadcastWith(): array
    {
        return ['reason' => $this->reason];
    }
}
