<?php

namespace App\Services\Schedule\Finite;

/**
 * What is already booked while a schedule is being built: busy intervals per
 * resource, and operator demand per line.
 *
 * Resource keys are `ws:<id>` for a workstation and `line:<id>` for a line used
 * as a whole. A line booked as a whole conflicts with every station on it and
 * vice versa — the caller passes both keys when checking — which is the same
 * rule the planner's line-level conflict detection already applies.
 *
 * Intervals are half-open [start, end) in epoch minutes.
 */
class Timeline
{
    /** @var array<string, list<array{start: int, end: int, attributes: array<string, mixed>, operation: ?OperationDemand, setup: int}>> */
    private array $busy = [];

    /** @var array<int, list<array{start: int, end: int, operators: int, skills: list<int>}>> */
    private array $labor = [];

    /**
     * @param  array<string, mixed>  $attributes  changeover dimensions of what runs there
     * @param  OperationDemand|null  $operation  the operation booked by this run (null for committed load)
     * @param  int  $setup  setup minutes the booking reserved
     */
    public function book(string $key, int $start, int $end, array $attributes = [], ?OperationDemand $operation = null, int $setup = 0): void
    {
        if ($end <= $start) {
            return;
        }
        $this->busy[$key][] = ['start' => $start, 'end' => $end, 'attributes' => $attributes, 'operation' => $operation, 'setup' => $setup];
        usort($this->busy[$key], fn ($a, $b) => $a['start'] <=> $b['start']);
    }

    /**
     * @param  list<int>  $skills
     */
    public function bookLabor(int $lineKey, int $start, int $end, int $operators, array $skills = []): void
    {
        if ($end <= $start) {
            return;
        }
        $this->labor[$lineKey][] = ['start' => $start, 'end' => $end, 'operators' => $operators, 'skills' => $skills];
    }

    /**
     * The latest end among bookings overlapping [start, end) on any of the
     * keys — where the next attempt should start — or null when it is free.
     *
     * @param  list<string>  $keys
     */
    public function conflictEnd(array $keys, int $start, int $end): ?int
    {
        $latest = null;
        foreach ($keys as $key) {
            foreach ($this->busy[$key] ?? [] as $slot) {
                if ($slot['start'] < $end && $slot['end'] > $start) {
                    $latest = max($latest ?? $slot['end'], $slot['end']);
                }
            }
        }

        return $latest;
    }

    /**
     * The first booking on the resource starting at or after $t — the job whose
     * predecessor changes if something is inserted just before it.
     *
     * @return array{start: int, end: int, attributes: array<string, mixed>, operation: ?OperationDemand, setup: int}|null
     */
    public function nextBooking(string $key, int $t): ?array
    {
        foreach ($this->busy[$key] ?? [] as $slot) {
            if ($slot['start'] >= $t) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * What ran last on the resource before $t — for sequence-dependent setup.
     *
     * @return array<string, mixed>|null
     */
    public function previousAttributes(string $key, int $t): ?array
    {
        $previous = null;
        foreach ($this->busy[$key] ?? [] as $slot) {
            if ($slot['end'] <= $t && ($previous === null || $slot['end'] >= $previous['end'])) {
                $previous = $slot;
            }
        }

        return $previous['attributes'] ?? null;
    }

    /**
     * The most operators committed on a line at any one moment within
     * [start, end) — the peak of concurrent demand, not the sum of every
     * booking that touches the window: two jobs that run one after the other
     * inside the window never need their crews at the same time. With skills
     * given, only bookings needing one of them count — those are the people
     * competing for the same qualification.
     *
     * @param  list<int>|null  $skills
     */
    public function laborInUse(int $lineKey, int $start, int $end, ?array $skills = null): int
    {
        $events = [];
        foreach ($this->labor[$lineKey] ?? [] as $slot) {
            if ($slot['start'] >= $end || $slot['end'] <= $start) {
                continue;
            }
            if ($skills !== null && array_intersect($skills, $slot['skills']) === []) {
                continue;
            }
            $events[] = [max($start, $slot['start']), $slot['operators']];
            $events[] = [min($end, $slot['end']), -$slot['operators']];
        }

        // Releases sort before claims at the same minute: half-open intervals.
        usort($events, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $current = 0;
        $peak = 0;
        foreach ($events as [, $delta]) {
            $current += $delta;
            $peak = max($peak, $current);
        }

        return $peak;
    }

    /** The first booking end on the line after $t, to retry once people free up. */
    public function nextLaborRelease(int $lineKey, int $t): ?int
    {
        $next = null;
        foreach ($this->labor[$lineKey] ?? [] as $slot) {
            if ($slot['end'] > $t) {
                $next = min($next ?? $slot['end'], $slot['end']);
            }
        }

        return $next;
    }

    /**
     * The first booking end on any of the lines after $t.
     *
     * @param  list<int>  $lineKeys
     */
    public function nextLaborReleaseAmong(array $lineKeys, int $t): ?int
    {
        $next = null;
        foreach ($lineKeys as $lineKey) {
            $release = $this->nextLaborRelease($lineKey, $t);
            if ($release !== null) {
                $next = min($next ?? $release, $release);
            }
        }

        return $next;
    }

    /**
     * Operator demand per line through [start, end), as the stretches where it
     * stays the same: each segment is half-open and lists, per line with any
     * booking active in it, the operators booked there. Stretches with nothing
     * booked on any of the lines are left out; consecutive stretches with the
     * same demand are merged.
     *
     * @param  list<int>  $lineKeys
     * @return list<array{start: int, end: int, demand: array<int, int>}>
     */
    public function laborSegments(array $lineKeys, int $start, int $end): array
    {
        return $this->segments($lineKeys, $start, $end, fn (int $lineKey, array $skills) => $lineKey);
    }

    /**
     * The same stretches as {@see laborSegments()}, with the booked operators
     * split by what their step needs: each demand key is
     * {@see self::slotKind()} of a line and the skills its booking requires.
     *
     * @param  list<int>  $lineKeys
     * @return list<array{start: int, end: int, demand: array<string, int>}>
     */
    public function skilledLaborSegments(array $lineKeys, int $start, int $end): array
    {
        return $this->segments($lineKeys, $start, $end, fn (int $lineKey, array $skills) => self::slotKind($lineKey, $skills));
    }

    /**
     * `<line>|<sorted skill ids>`, e.g. `4|2,7`; `4|` for a booking needing no skill.
     *
     * @param  list<int>  $skills
     */
    public static function slotKind(int $lineKey, array $skills): string
    {
        $skills = array_map('intval', $skills);
        sort($skills);

        return $lineKey.'|'.implode(',', array_unique($skills));
    }

    /**
     * The line and the skills of a {@see self::slotKind()}.
     *
     * @return array{0: int, 1: list<int>}
     */
    public static function parseSlotKind(string $kind): array
    {
        [$line, $skills] = explode('|', $kind, 2);

        return [(int) $line, $skills === '' ? [] : array_map('intval', explode(',', $skills))];
    }

    /**
     * @param  list<int>  $lineKeys
     * @param  callable(int, list<int>): (int|string)  $kindOf
     * @return list<array{start: int, end: int, demand: array<int|string, int>}>
     */
    private function segments(array $lineKeys, int $start, int $end, callable $kindOf): array
    {
        $points = [$start => true, $end => true];
        $active = [];
        foreach ($lineKeys as $lineKey) {
            foreach ($this->labor[$lineKey] ?? [] as $slot) {
                if ($slot['start'] >= $end || $slot['end'] <= $start || $slot['operators'] <= 0) {
                    continue;
                }
                $from = max($start, $slot['start']);
                $to = min($end, $slot['end']);
                $points[$from] = true;
                $points[$to] = true;
                $active[] = [$kindOf($lineKey, $slot['skills']), $from, $to, $slot['operators']];
            }
        }
        if ($active === []) {
            return [];
        }

        $points = array_keys($points);
        sort($points);

        $segments = [];
        for ($i = 0, $n = count($points) - 1; $i < $n; $i++) {
            [$from, $to] = [$points[$i], $points[$i + 1]];
            $demand = [];
            foreach ($active as [$kind, $s, $e, $operators]) {
                if ($s <= $from && $e >= $to) {
                    $demand[$kind] = ($demand[$kind] ?? 0) + $operators;
                }
            }
            if ($demand === []) {
                continue;
            }
            ksort($demand);
            $last = array_key_last($segments);
            if ($last !== null && $segments[$last]['end'] === $from && $segments[$last]['demand'] === $demand) {
                $segments[$last]['end'] = $to;

                continue;
            }
            $segments[] = ['start' => $from, 'end' => $to, 'demand' => $demand];
        }

        return $segments;
    }

    /**
     * Whether any of the lines has operators booked within [start, end).
     *
     * @param  list<int>  $lineKeys
     */
    public function hasLaborBetween(array $lineKeys, int $start, int $end): bool
    {
        foreach ($lineKeys as $lineKey) {
            foreach ($this->labor[$lineKey] ?? [] as $slot) {
                if ($slot['start'] < $end && $slot['end'] > $start && $slot['operators'] > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether any of the lines has a booking within [start, end) whose step
     * requires a skill.
     *
     * @param  list<int>  $lineKeys
     */
    public function hasSkilledLaborBetween(array $lineKeys, int $start, int $end): bool
    {
        foreach ($lineKeys as $lineKey) {
            foreach ($this->labor[$lineKey] ?? [] as $slot) {
                if ($slot['start'] < $end && $slot['end'] > $start && $slot['operators'] > 0 && $slot['skills'] !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Booked working-time footprint per resource key, for the load summary.
     *
     * @return array<string, list<array{start: int, end: int}>>
     */
    public function bookings(): array
    {
        return array_map(
            fn ($slots) => array_map(fn ($s) => ['start' => $s['start'], 'end' => $s['end']], $slots),
            $this->busy,
        );
    }
}
