<?php

namespace Modules\ExampleHooks\Listeners;

use App\Events\Batch\BatchCreated;
use Illuminate\Support\Facades\Log;

class LogBatchCreated
{
    public function handle(BatchCreated $event): void
    {
        Log::channel('daily')->info('[ExampleHooks] Batch created', [
            'batch_number'   => $event->batch->batch_number,
            'work_order_id'  => $event->batch->work_order_id,
            'target_qty'     => $event->batch->target_qty,
        ]);
    }
}
