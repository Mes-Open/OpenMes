<?php

namespace App\Services\Workforce;

use App\Models\Crew;
use App\Models\CrewBreakWindow;
use App\Models\Worker;
use App\Models\WorkerAbsence;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Worker availability derived from recorded absences and crew break windows.
 * The seam other features (assignment warnings, capacity planning) hook into.
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

    /**
     * Is the worker on a crew break at the given moment? A worker with no crew
     * is never on a crew break. Honours weekday + time-of-day of active windows.
     */
    public function isOnBreak(Worker $worker, CarbonInterface $moment): bool
    {
        if (! $worker->crew_id) {
            return false;
        }

        return CrewBreakWindow::query()
            ->active()
            ->where('crew_id', $worker->crew_id)
            ->get()
            ->contains(fn (CrewBreakWindow $w) => $w->coversTime($moment));
    }

    /**
     * Active break windows for a crew that apply on the given date, ordered by
     * start time — for rendering a day's break blocks.
     *
     * @return Collection<int, CrewBreakWindow>
     */
    public function crewBreakWindowsOn(Crew|int $crew, CarbonInterface $date): Collection
    {
        $crewId = $crew instanceof Crew ? $crew->id : $crew;

        return CrewBreakWindow::query()
            ->active()
            ->where('crew_id', $crewId)
            ->orderBy('start_time')
            ->get()
            ->filter(fn (CrewBreakWindow $w) => $w->appliesOn($date))
            ->values();
    }
}
