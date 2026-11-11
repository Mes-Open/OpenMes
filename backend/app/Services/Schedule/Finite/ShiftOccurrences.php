<?php

namespace App\Services\Schedule\Finite;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Concrete runs of the shift calendar — each one the shift it belongs to and
 * the date it started on — for the scheduler's minute arithmetic.
 *
 * The rules are those of the capacity grid: active shifts (global plus the
 * line's own) on the weekdays they run, an unset weekday list meaning every
 * day, an end at or before the start crossing midnight and a zero-length shift
 * running never.
 *
 * The start date is what an absence refers to: an absence recorded for D takes
 * a person out of the shifts that start on D (the payroll convention), so the
 * night shift Monday 22:00 – Tuesday 06:00 is Monday's. {@see attributedDates()}
 * maps a window onto those dates.
 *
 * Times are whole minutes since the Unix epoch, as in {@see ResourceCalendar}.
 */
final class ShiftOccurrences
{
    /**
     * Every shift run overlapping [$from, $to), unclipped and unmerged, sorted
     * by start. A zero-length window returns the runs containing $from.
     *
     * @param  Collection<int, Shift>  $shifts
     * @return list<array{start: int, end: int, date: string}>
     */
    public static function occurrences(Collection $shifts, ?int $lineId, int $from, int $to): array
    {
        $shifts = self::forLine($shifts, $lineId);
        $out = [];

        // Start a day early so a night shift that began yesterday still counts.
        $day = ResourceCalendar::toCarbon($from)->startOfDay()->subDay();
        $last = ResourceCalendar::toCarbon(max($from, $to))->startOfDay();
        while ($day->lte($last)) {
            foreach (self::runsOn($shifts, $day) as $run) {
                $overlaps = $to > $from
                    ? $run['start'] < $to && $run['end'] > $from
                    : $run['start'] <= $from && $run['end'] > $from;
                if ($overlaps) {
                    $out[] = $run;
                }
            }
            $day->addDay();
        }

        usort($out, fn ($x, $y) => [$x['start'], $x['end']] <=> [$y['start'], $y['end']]);

        return $out;
    }

    /**
     * The dates [$from, $to) belongs to for absence purposes: a minute inside
     * a shift run belongs to the date that run started, a minute outside every
     * run to its own calendar date. Sorted, unique.
     *
     * @param  Collection<int, Shift>  $shifts
     * @return list<string>
     */
    public static function attributedDates(Collection $shifts, ?int $lineId, int $from, int $to): array
    {
        $runs = self::occurrences($shifts, $lineId, $from, $to);
        $dates = array_map(fn (array $run) => $run['date'], $runs);

        if ($to <= $from) {
            if ($dates === []) {
                $dates[] = ResourceCalendar::toCarbon($from)->toDateString();
            }
        } else {
            // Calendar dates of whatever part of the window no run covers.
            $cursor = $from;
            foreach ($runs as $run) {
                if ($run['start'] > $cursor) {
                    $dates = [...$dates, ...self::calendarDates($cursor, min($run['start'], $to))];
                }
                $cursor = max($cursor, $run['end']);
                if ($cursor >= $to) {
                    break;
                }
            }
            if ($cursor < $to) {
                $dates = [...$dates, ...self::calendarDates($cursor, $to)];
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * The first shift start strictly after $t and before $to, or null.
     *
     * @param  Collection<int, Shift>  $shifts
     */
    public static function nextStartAfter(Collection $shifts, ?int $lineId, int $t, int $to): ?int
    {
        $shifts = self::forLine($shifts, $lineId);
        if ($shifts->isEmpty()) {
            return null;
        }

        // A run starts on its own date, so nothing before $t's date can follow $t.
        $day = ResourceCalendar::toCarbon($t)->startOfDay();
        while (ResourceCalendar::toMinutes($day) < $to) {
            $next = null;
            foreach (self::runsOn($shifts, $day) as $run) {
                if ($run['start'] > $t && $run['start'] < $to) {
                    $next = $next === null ? $run['start'] : min($next, $run['start']);
                }
            }
            if ($next !== null) {
                return $next;
            }
            $day->addDay();
        }

        return null;
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     * @return Collection<int, Shift>
     */
    private static function forLine(Collection $shifts, ?int $lineId): Collection
    {
        return $shifts->filter(fn (Shift $s) => $s->line_id === null || ($lineId !== null && (int) $s->line_id === $lineId));
    }

    /**
     * The runs of $shifts that start on $day.
     *
     * @param  Collection<int, Shift>  $shifts
     * @return list<array{start: int, end: int, date: string}>
     */
    private static function runsOn(Collection $shifts, Carbon $day): array
    {
        $dow = (int) $day->format('N');
        $date = $day->toDateString();
        $runs = [];
        foreach ($shifts as $shift) {
            $days = $shift->days_of_week;
            if (is_array($days) && ! in_array($dow, $days, true)) {
                continue;
            }
            $start = $day->copy()->setTimeFromTimeString($shift->start_time);
            $end = $day->copy()->setTimeFromTimeString($shift->end_time);
            if ($end->equalTo($start)) {
                continue;
            }
            if ($end->lessThan($start)) {
                $end->addDay();
            }
            $runs[] = ['start' => ResourceCalendar::toMinutes($start), 'end' => ResourceCalendar::toMinutes($end), 'date' => $date];
        }

        return $runs;
    }

    /**
     * Calendar dates touched by [$a, $b), $b > $a.
     *
     * @return list<string>
     */
    private static function calendarDates(int $a, int $b): array
    {
        $dates = [];
        $last = ResourceCalendar::toCarbon($b - 1)->toDateString();
        for ($day = ResourceCalendar::toCarbon($a)->startOfDay(); ; $day->addDay()) {
            $dates[] = $day->toDateString();
            if ($dates[count($dates) - 1] >= $last) {
                break;
            }
        }

        return $dates;
    }
}
