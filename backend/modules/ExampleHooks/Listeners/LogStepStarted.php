<?php

namespace Modules\ExampleHooks\Listeners;

use App\Events\BatchStep\StepStarted;
use Illuminate\Support\Facades\Log;

class LogStepStarted
{
    public function handle(StepStarted $event): void
    {
        Log::channel('daily')->info('[ExampleHooks] Step started', [
            'step_id'     => $event->batchStep->id,
            'step_name'   => $event->batchStep->name,
            'batch_id'    => $event->batchStep->batch_id,
            'started_by'  => $event->batchStep->started_by_id,
        ]);
    }
}
