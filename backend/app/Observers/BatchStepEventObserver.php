<?php

namespace App\Observers;

use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Models\BatchStep;

/**
 * Dispatches the batch-step domain events from the model lifecycle: a step
 * entering IN_PROGRESS is "started", entering DONE is "completed". Fires on every
 * path that transitions a step (operator panel, API, services), so module hooks
 * see real production progress. These events were defined but never dispatched.
 */
class BatchStepEventObserver
{
    public function updated(BatchStep $batchStep): void
    {
        if (! $batchStep->wasChanged('status')) {
            return;
        }

        match ($batchStep->status) {
            BatchStep::STATUS_IN_PROGRESS => event(new StepStarted($batchStep)),
            BatchStep::STATUS_DONE => event(new StepCompleted($batchStep)),
            default => null,
        };
    }
}
