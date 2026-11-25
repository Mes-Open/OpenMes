<?php

namespace App\Services\Schedule\Finite;

use App\Extension\Contracts\ExplainsWorkforceCapacity;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Extension\Contracts\WorkforceRosterAware;
use App\Models\Shift;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Counts people from what core already records: the operator accounts assigned
 * to a line (Admin → Lines → Operators), the active workers assigned to its
 * workstations and the workers of any crew the workforce module assigns to it —
 * once each, an account linked to a worker being that worker — less those the
 * workforce module reports absent on a date the window belongs to.
 *
 * An absence on date D takes a person out of the shifts that start on D, the
 * payroll convention: each minute of the window counts towards the start date
 * of the shift it lies in ({@see ShiftOccurrences::attributedDates()}), a
 * minute outside every shift towards its calendar date. So the night shift
 * Monday 22:00 – Tuesday 06:00 is Monday's: a Tuesday absence leaves it alone,
 * a Monday absence blocks it.
 *
 * A line nobody is assigned to is not tracked, so it answers null and the
 * scheduler stays machine-constrained there — an installation that never
 * assigned workers schedules exactly as before. Skills count only where the
 * workforce module adds `Worker::skills()`; without it qualification is not
 * tracked either. Break windows are left to the shift calendar: they are short
 * compared with the windows the scheduler asks about.
 *
 * The person keys are the same on every line, so the scheduler can see that
 * someone assigned to two lines is one person ({@see WorkforceRosterAware}).
 *
 * One instance serves one scheduling run, so the assignments, the shifts and
 * each day's absences are read once.
 */
class AssignedStaffCapacityProvider implements ExplainsWorkforceCapacity, WorkforceCapacityProvider, WorkforceRosterAware
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

    /** @var array<string, string> person key => display name, as looked up so far */
    private array $names = [];

    /** @var array<string, list<int>> date => absent worker ids */
    private array $absent = [];

    /** @var Collection<int, Shift>|null */
    private ?Collection $shifts = null;

    /** @var array<int, true>|null lines with a shift of their own */
    private ?array $ownCalendar = null;

    /**
     * Absent worker ids per calendar and window. Every line on the global
     * calendar attributes a window to the same dates, so the scheduler asking
     * about several lines for one window works the dates out once.
     *
     * @var array<string, list<int>>
     */
    private array $absentInWindow = [];

    public function __construct(private readonly WorkforceProvider $people) {}

    public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int
    {
        $present = $this->present($lineId, $start, $end);

        return $present === null ? null : count($present);
    }

    public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int
    {
        $qualified = $this->qualifiedOperatorKeys($lineId, $start, $end, $skillIds);

        return $qualified === null ? null : count($qualified);
    }

    public function qualifiedOperatorKeys(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?array
    {
        $present = $this->present($lineId, $start, $end);
        $skills = $this->skills();
        if ($present === null || $skills === null) {
            return null;
        }

        return array_values(array_filter($present, fn (string $key) => str_starts_with($key, 'w:')
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
            $this->absentBetween($lineId, $start, $end),
            fn (int $id) => in_array('w:'.$id, $staff, true),
        ));
    }

    public function assignedOperatorKeys(): array
    {
        return array_filter($this->staff(), fn ($line) => is_int($line), ARRAY_FILTER_USE_KEY);
    }

    public function presentOperatorKeys(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?array
    {
        return $this->present($lineId, $start, $end);
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

    public function skillHolders(?int $lineId): ?array
    {
        $staff = $this->staff()[$lineId ?? 'none'] ?? [];
        $skills = $this->skills();
        if ($staff === [] || $skills === null) {
            return null;
        }

        $holders = [];
        foreach ($staff as $key) {
            if (! str_starts_with($key, 'w:')) {
                continue; // an account with no worker record holds no skills
            }
            foreach ($skills[(int) substr($key, 2)] ?? [] as $skillId) {
                $holders[$skillId][] = $key;
            }
        }
        ksort($holders);

        return $holders;
    }

    public function operatorNames(array $keys): array
    {
        $missing = ['w' => [], 'u' => []];
        foreach (array_unique($keys) as $key) {
            [$kind, $id] = array_pad(explode(':', $key, 2), 2, '');
            if (! isset($this->names[$key]) && isset($missing[$kind]) && ctype_digit($id)) {
                $missing[$kind][] = (int) $id;
            }
        }
        if ($missing['w'] !== []) {
            foreach ($this->workerNames($missing['w']) as $id => $name) {
                $this->names['w:'.$id] = $name;
            }
        }
        if ($missing['u'] !== []) {
            foreach (User::whereKey($missing['u'])->pluck('name', 'id') as $id => $name) {
                $this->names['u:'.$id] = (string) $name;
            }
        }

        return array_intersect_key($this->names, array_flip($keys));
    }

    /**
     * Workers staffing the line who are not absent on any date the window
     * belongs to;
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

        $away = array_map(fn (int $id) => 'w:'.$id, $this->absentBetween($lineId, $start, $end));

        return array_values(array_diff($staff, $away));
    }

    /** @return array<int|string, list<string>> */
    private function staff(): array
    {
        return $this->staff ??= AssignedStaff::byLine($this->people);
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

    /**
     * Workers absent on a date [$start, $end) belongs to on the line's shift
     * calendar.
     *
     * @return list<int>
     */
    private function absentBetween(?int $lineId, CarbonInterface $start, CarbonInterface $end): array
    {
        $this->shifts ??= Shift::where('is_active', true)->get();
        $this->ownCalendar ??= $this->shifts
            ->flatMap(fn (Shift $s) => $s->lineIds() ?? [])
            ->mapWithKeys(fn (int $lineId) => [$lineId => true])->all();
        $from = ResourceCalendar::toMinutes($start);
        $to = ResourceCalendar::toMinutes($end);
        $calendar = $lineId !== null && isset($this->ownCalendar[$lineId]) ? (string) $lineId : 'global';
        $memo = $calendar.'|'.$from.'|'.$to;
        if (isset($this->absentInWindow[$memo])) {
            return $this->absentInWindow[$memo];
        }
        if (count($this->absentInWindow) > 20000) {
            $this->absentInWindow = [];
        }

        $dates = ShiftOccurrences::attributedDates($this->shifts, $lineId, $from, $to);

        $ids = [];
        foreach ($dates as $date) {
            $ids = [...$ids, ...($this->absent[$date] ??= array_map(
                'intval',
                $this->people->absentWorkerIds(Carbon::parse($date, config('app.timezone'))->startOfDay()),
            ))];
        }

        return $this->absentInWindow[$memo] = array_values(array_unique($ids));
    }
}
