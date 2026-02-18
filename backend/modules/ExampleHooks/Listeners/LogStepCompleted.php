<?php

namespace Modules\ExampleHooks\Listeners;

use App\Events\BatchStep\StepCompleted;
use Illuminate\Support\Facades\Log;

class LogStepCompleted
{
    public function handle(StepCompleted $event): void
    {
        Log::channel('daily')->info('[ExampleHooks] Step completed', [
            'step_id'       => $event->batchStep->id,
            'step_name'     => $event->batchStep->name,
            'batch_id'      => $event->batchStep->batch_id,
            'completed_by'  => $event->batchStep->completed_by_id,
        ]);
    }
}
