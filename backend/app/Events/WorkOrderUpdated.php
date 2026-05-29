<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Pushed when a specific work order changes (status, qty, batch progress).
 * Subscribers on `private-workorder.{id}` get a wake-up to refetch.
 *
 * Coexists with ScheduleUpdated — that's the broad "something changed
 * somewhere on the schedule" channel; this one is laser-focused on a single
 * WO so the run/[batchId] screen doesn't need to filter incoming events.
 */
class WorkOrderUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $workOrderId,
        public string $kind = 'change',
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workorder.{$this->workOrderId}")];
    }

    public function broadcastAs(): string
    {
        return 'workorder.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'work_order_id' => $this->workOrderId,
            'kind' => $this->kind,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastWhen(): bool
    {
        $mode = DB::table('system_settings')->where('key', 'realtime_mode')->value('value');

        return $mode === 'websocket';
    }
}
