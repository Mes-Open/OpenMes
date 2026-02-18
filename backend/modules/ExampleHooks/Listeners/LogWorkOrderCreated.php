<?php

namespace Modules\ExampleHooks\Listeners;

use App\Events\WorkOrder\WorkOrderCreated;
use Illuminate\Support\Facades\Log;

class LogWorkOrderCreated
{
    public function handle(WorkOrderCreated $event): void
    {
        Log::channel('daily')->info('[ExampleHooks] Work order created', [
            'order_no'    => $event->workOrder->order_no,
            'planned_qty' => $event->workOrder->planned_qty,
            'line_id'     => $event->workOrder->line_id,
        ]);
    }
}
