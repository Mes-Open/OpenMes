<?php

namespace Modules\ExampleHooks\Listeners;

use App\Events\WorkOrder\WorkOrderCompleted;
use Illuminate\Support\Facades\Log;

class LogWorkOrderCompleted
{
    public function handle(WorkOrderCompleted $event): void
    {
        Log::channel('daily')->info('[ExampleHooks] Work order completed', [
            'order_no'     => $event->workOrder->order_no,
            'produced_qty' => $event->workOrder->produced_qty,
        ]);
    }
}
