<?php

namespace App\Extension\Contracts\Null;

use App\Extension\Contracts\WorkforceProvider;
use App\Models\Worker;
use Carbon\CarbonInterface;

/**
 * What the application believes about people when nothing records it.
 *
 * Every answer here is the neutral one — deliberately the same answer the real
 * implementation gives when its tables are empty, which is what makes binding
 * this a no-op rather than a behaviour change. It never returns "unknown": a
 * caller that has to branch on "I don't know" is a caller that will eventually
 * branch wrong.
 */
class NullWorkforceProvider implements WorkforceProvider
{
    public function isAvailable(Worker $worker, CarbonInterface $start, CarbonInterface $end): bool
    {
        return true;
    }

    public function isAbsentOn(Worker $worker, CarbonInterface $date): bool
    {
        return false;
    }

    public function absentWorkerIds(CarbonInterface $date): array
    {
        return [];
    }

    public function isOnBreak(Worker $worker, CarbonInterface $moment): bool
    {
        return false;
    }

    /** No wage data — the caller uses the worker's own rate or the default. */
    public function hourlyRate(Worker $worker): ?float
    {
        return null;
    }

    public function crewOptions(): array
    {
        return [];
    }

    public function wageGroupOptions(): array
    {
        return [];
    }

    public function skillOptions(): array
    {
        return [];
    }

    public function personnelClassOptions(): array
    {
        return [];
    }

    public function certificationLevels(): array
    {
        return [];
    }

    public function skillNames(array $ids): array
    {
        return [];
    }

    public function crewsForCapacity(): \Illuminate\Support\Collection
    {
        return collect();
    }
}
