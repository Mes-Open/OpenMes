<?php

namespace App\Services\Schedule\Finite;

use App\Extension\Contracts\ExplainsWorkforceCapacity;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Counts people from what core already records: the operator accounts assigned
 * to a line (Admin → Lines → Operators), the active workers assigned to its
 * workstations and the workers of any crew the workforce module assigns to it —
 * once each, an account linked to a worker being that worker — less those the
 * workforce module reports absent on a day of the window.
 *
 * A line nobody is assigned to is not tracked, so it answers null and the
 * scheduler stays machine-constrained there — an installation that never
 * assigned workers schedules exactly as before. Skills count only where the
 * workforce module adds `Worker::skills()`; without it qualification is not
 * tracked either. Break windows are left to the shift calendar: they are short
 * compared with the windows the scheduler asks about.
 *
 * One instance serves one scheduling run, so the assignments and each day's
 * absences are read once.
 */
class AssignedStaffCapacityProvider implements ExplainsWorkforceCapacity, WorkforceCapacityProvider
{
    /**
     * line id (or 'none') => people, keyed `w:<worker id>` or, for an account
     * with no worker record, `u:<user id>`.
     *
     * @var array<int|string, list<string>>|null
     */
    private ?array $staff = null;

    /** @var array<int, list<int>>|null worker id => skill ids; null when skills are not recorded */
    private ?array $skills = null;

    private bool $skillsLoaded = false;

    /** @var array<string, list<int>> date => absent worker ids */
    private array $absent = [];

    public function __construct(private readonly WorkforceProvider $people) {}

    public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int
    {
        $present = $this->present($lineId, $start, $end);

        return $present === null ? null : count($present);
    }

    public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int
    {
        $present = $this->present($lineId, $start, $end);
        $skills = $this->skills();
        if ($present === null || $skills === null) {
            return null;
        }

        return count(array_filter($present, fn (string $key) => str_starts_with($key, 'w:')
            && array_diff($skillIds, $skills[(int) substr($key, 2)] ?? []) === []));
    }

    public function assignedCount(?int $lineId): ?int
    {
        $count = count($this->staff()[$lineId ?? 'none'] ?? []);

        return $count === 0 ? null : $count;
    }

    public function absentWorkerIds(?int $lineId, CarbonInterface $start, CarbonInterface $end): array
    {
        $staff = $this->staff()[$lineId ?? 'none'] ?? [];

        return array_values(array_filter(
            $this->absentBetween($start, $end),
            fn (int $id) => in_array('w:'.$id, $staff, true),
        ));
    }

    public function workerNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Worker::whereKey($ids)->pluck('name', 'id')->map(fn ($name) => (string) $name)->all();
    }

    public function skillNames(array $ids): array
    {
        if ($ids === [] || ! (new Worker)->isRelation('skills')) {
            return [];
        }

        $related = (new Worker)->skills()->getRelated();
        if (! Schema::hasColumn($related->getTable(), 'name')) {
            return [];
        }

        return $related->newQuery()->whereKey($ids)->pluck('name', $related->getKeyName())
            ->map(fn ($name) => (string) $name)->all();
    }

    /**
     * Workers staffing the line who are not absent on any day of the window;
     * null when nobody is assigned to the line at all.
     *
     * @return list<string>|null
     */
    private function present(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?array
    {
        $staff = $this->staff()[$lineId ?? 'none'] ?? [];
        if ($staff === []) {
            return null;
        }

        $away = array_map(fn (int $id) => 'w:'.$id, $this->absentBetween($start, $end));

        return array_values(array_diff($staff, $away));
    }

    /** @return array<int|string, list<string>> */
    private function staff(): array
    {
        if ($this->staff !== null) {
            return $this->staff;
        }

        $staff = [];
        $users = User::with('lines:id')->whereHas('lines')->get(['id', 'worker_id']);
        foreach ($users as $user) {
            foreach ($user->lines as $line) {
                $staff[$line->id][] = $user->worker_id ? 'w:'.$user->worker_id : 'u:'.$user->id;
            }
        }

        $workers = Worker::active()->where('is_logistics', false)->with('workstation:id,line_id')->get(['id', 'workstation_id']);
        foreach ($workers as $worker) {
            $staff[$worker->workstation?->line_id ?? 'none'][] = 'w:'.$worker->id;
        }

        // A crew explicitly assigned to lines staffs all of them.
        foreach ($this->people->crewsForCapacity() as $crew) {
            if (! $crew->relationLoaded('lines') || $crew->lines->isEmpty()) {
                continue;
            }
            $members = $crew->workers->filter(fn ($w) => ($w->is_active ?? true) && ! ($w->is_logistics ?? false))
                ->map(fn ($w) => 'w:'.$w->id)->all();
            foreach ($crew->lines as $line) {
                $staff[$line->id] = [...($staff[$line->id] ?? []), ...$members];
            }
        }

        return $this->staff = array_map(fn (array $ids) => array_values(array_unique($ids)), $staff);
    }

    /** @return array<int, list<int>>|null */
    private function skills(): ?array
    {
        if ($this->skillsLoaded) {
            return $this->skills;
        }
        $this->skillsLoaded = true;

        if (! (new Worker)->isRelation('skills')) {
            return $this->skills = null;
        }

        return $this->skills = Worker::active()->with('skills')->get()
            ->mapWithKeys(fn (Worker $w) => [$w->id => $w->skills->pluck('id')->map(fn ($id) => (int) $id)->all()])
            ->all();
    }

    /** @return list<int> */
    private function absentBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $ids = [];
        for ($day = $start->copy()->startOfDay(); $day->lessThan($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $ids = [...$ids, ...($this->absent[$key] ??= array_map('intval', $this->people->absentWorkerIds($day)))];
        }

        return array_values(array_unique($ids));
    }
}
