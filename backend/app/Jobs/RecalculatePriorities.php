<?php

namespace App\Jobs;

use App\Models\WorkOrder;
use App\Services\WorkOrder\PriorityScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-scores every active work order from the current priority rules. Dispatched
 * after a rule/band change so the HTTP request that made the change isn't
 * blocked while (potentially thousands of) orders are recomputed. The hourly
 * `priority:recalculate` command does the same job on a schedule.
 */
class RecalculatePriorities implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PriorityScoringService $service): void
    {
        WorkOrder::query()
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->with('customer')
            ->chunkById(200, function ($orders) use ($service): void {
                foreach ($orders as $workOrder) {
                    $service->apply($workOrder, persist: true);
                }
            });
    }
}
