<?php

namespace App\Observers;

use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Models\BatchStep;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches the batch-step domain events from the model lifecycle: a step
 * entering IN_PROGRESS is "started", entering DONE is "completed". Fires on every
 * path that transitions a step (operator panel, API, services), so module hooks
 * see real production progress. These events were defined but never dispatched.
 * The dispatch is guarded so a throwing module listener can't break the save.
 */
class BatchStepEventObserver
{
    public function updated(BatchStep $batchStep): void
    {
        if (! $batchStep->wasChanged('status')) {
            return;
        }

        $event = match ($batchStep->status) {
            BatchStep::STATUS_IN_PROGRESS => new StepStarted($batchStep),
            BatchStep::STATUS_DONE => new StepCompleted($batchStep),
            default => null,
        };

        if ($event !== null) {
            try {
                event($event);
            } catch (\Throwable $e) {
                Log::warning('BatchStep hook failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
