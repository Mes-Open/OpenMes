<?php

namespace App\Services\Schedule\Finite;

use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceRosterAware;

/**
 * How many people a line really has for a stretch of time, given what is
 * booked on it and on every line it shares people with — the one count both
 * the automatic scheduler's staffing grid and the Production Planner's
 * staffing badges show.
 *
 * Present people come from the {@see WorkforceCapacityProvider}, absences
 * already applied. When the provider names its people
 * ({@see WorkforceRosterAware}), someone assigned to several lines is one
 * person: within each stretch of constant booked load, the work booked on the
 * other lines of the group takes its people first ({@see SharedOperatorPool}),
 * and only who is left counts for this line. A provider that only counts keeps
 * every line on its own, exactly as the scheduler does.
 *
 * The result is a pair the planner can read at a glance: `available` against
 * `needed`, the peak of operators booked on the line. `available` is
 * `needed` plus the tightest slack of any stretch in which the line has work,
 * so the pair is short exactly when some stretch is short, tight when the
 * tightest stretch has no one to spare, and has spare people otherwise. In a
 * window with no work on the line, `available` is simply who is free for it.
 *
 * Pure apart from the provider: no database, no clock.
 */
final class StaffingCounter
{
    public function __construct(
        private readonly WorkforceCapacityProvider $workforce,
        private readonly Timeline $timeline,
        private readonly ?SharedOperatorPool $pool,
    ) {}

    /**
     * The shared-people pool for the given lines, or null when the provider
     * cannot say who its people are.
     *
     * @param  list<int>  $lineIds
     */
    public static function poolFor(WorkforceCapacityProvider $workforce, array $lineIds): ?SharedOperatorPool
    {
        if (! $workforce instanceof WorkforceRosterAware) {
            return null;
        }

        return new SharedOperatorPool(array_intersect_key($workforce->assignedOperatorKeys(), array_flip($lineIds)));
    }

    /**
     * @return array{
     *     tracked: bool,
     *     present: ?int,
     *     available: ?int,
     *     needed: int,
     *     held_elsewhere: array<int, list<string>>
     * } present: people of the line not absent; available: of them, free for
     *   this line (see the class); held_elsewhere: other line id => person keys
     *   of this line that line's booked work holds in the tightest stretch
     */
    public function count(int $lineId, int $start, int $end): array
    {
        $from = ResourceCalendar::toCarbon($start);
        $to = ResourceCalendar::toCarbon($end);
        $needed = $this->timeline->laborInUse($lineId, $start, $end);

        $present = $this->workforce->availableOperators($lineId, $from, $to);
        if ($present === null) {
            return ['tracked' => false, 'present' => null, 'available' => null, 'needed' => $needed, 'held_elsewhere' => []];
        }

        $plain = ['tracked' => true, 'present' => $present, 'available' => $present, 'needed' => $needed, 'held_elsewhere' => []];
        if ($this->pool === null || ! $this->pool->isShared($lineId) || ! $this->workforce instanceof WorkforceRosterAware) {
            return $plain;
        }

        $own = $this->workforce->presentOperatorKeys($lineId, $from, $to);
        $others = array_values(array_diff($this->pool->groupOf($lineId), [$lineId]));
        if ($own === null || ! $this->timeline->hasLaborBetween($others, $start, $end)) {
            return $plain;
        }

        $candidates = [$lineId => $own];
        foreach ($others as $other) {
            $candidates[$other] = $this->workforce->presentOperatorKeys($other, $from, $to) ?? [];
        }

        $segments = $this->timeline->laborSegments([$lineId, ...$others], $start, $end);
        $working = array_values(array_filter($segments, fn (array $s) => ($s['demand'][$lineId] ?? 0) > 0));

        $worst = null;
        foreach ($working !== [] ? $working : $segments as $segment) {
            $booked = $segment['demand'];
            $mine = $booked[$lineId] ?? 0;
            unset($booked[$lineId]);
            $match = $this->pool->matchSlots($candidates, $booked, $own, count($own));
            $slack = $match['spare'] - $mine;
            if ($worst === null || $slack < $worst['slack']) {
                $worst = ['slack' => $slack, 'spare' => $match['spare'], 'holders' => $match['holders']];
            }
        }

        $held = [];
        foreach ($worst['holders'] ?? [] as $person => $kind) {
            if ((int) $kind !== $lineId) {
                $held[(int) $kind][] = (string) $person;
            }
        }
        ksort($held);

        return [
            'tracked' => true,
            'present' => $present,
            'available' => $working !== [] ? max(0, $needed + $worst['slack']) : $worst['spare'],
            'needed' => $needed,
            'held_elsewhere' => $held,
        ];
    }
}
