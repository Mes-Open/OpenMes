<?php

namespace App\Console\Commands;

use App\Models\WorkOrder;
use App\Services\WorkOrder\PriorityScoringService;
use Illuminate\Console\Command;

/**
 * Recompute automatic priority for all active work orders. Scheduled hourly so
 * time-based rules (hours-until-due) stay current as due dates approach.
 */
class RecalculatePriorityCommand extends Command
{
    protected $signature = 'priority:recalculate';

    protected $description = 'Recalculate work-order priority scores from the active rules';

    public function handle(PriorityScoringService $service): int
    {
        $count = 0;

        WorkOrder::query()
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->with('customer')
            ->chunkById(200, function ($orders) use ($service, &$count): void {
                foreach ($orders as $workOrder) {
                    $service->apply($workOrder, persist: true);
                    $count++;
                }
            });

        $this->info(sprintf('Recalculated priority for %d work order%s.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
