<?php

namespace App\Services\Workforce;

use App\Models\Worker;
use App\Models\WorkerAbsence;
use Carbon\CarbonInterface;

/**
 * Worker availability derived from recorded absences. The seam other features
 * (assignment warnings, capacity planning) hook into.
 */
class WorkerAvailabilityService
{
    /** Is the worker free across the whole inclusive [$start, $end] date span? */
    public function isAvailable(Worker $worker, CarbonInterface $start, CarbonInterface $end): bool
    {
        return ! $worker->absences()
            ->approved()
            ->overlapping($start->toDateString(), $end->toDateString())
            ->exists();
    }

    /** True if an approved absence covers the single given date. */
    public function isAbsentOn(Worker $worker, CarbonInterface $date): bool
    {
        return $worker->isAbsentOn($date);
    }

    /**
     * IDs of workers with an approved absence covering the given date — for
     * "absent today" badges and "available only" filters.
     *
     * @return array<int>
     */
    public function absentWorkerIds(CarbonInterface $date): array
    {
        return WorkerAbsence::query()
            ->approved()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->pluck('worker_id')
            ->unique()
            ->values()
            ->all();
    }
}
