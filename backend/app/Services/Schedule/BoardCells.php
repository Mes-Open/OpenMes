<?php

namespace App\Services\Schedule;

use App\Models\Shift;
use App\Models\WorkOrder;
use App\Services\Schedule\Finite\ResourceCalendar;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The Production Planner's line × shift cells, shared by everything that has
 * to read a placement the way the weekly board draws it: the board columns
 * (the distinct active shifts by time window, at most four a day), the window
 * of one cell, and the cells a day + shift span covers.
 *
 * A drop onto a weekly cell writes an envelope, not a minute plan: the shift
 * start to the midnight after the end date. Midnight is an exclusive end — it
 * closes the end date, it does not reach into the evening shifts of that
 * date. Whoever reads such an order books the cells it was dropped on, so
 * the automatic scheduler and the Planner count the same work in the same
 * shift.
 *
 * Pure apart from the models handed in.
 */
final class BoardCells
{
    public const MAX_COLUMNS = 4;

    /**
     * @param  Collection<int, Shift>  $shifts  active shifts
     * @return list<array{start: string, end: string, name: string}> 1-based by index + 1
     */
    public static function columns(Collection $shifts): array
    {
        return $shifts->sortBy([['sort_order', 'asc'], ['start_time', 'asc']])
            ->unique(fn (Shift $s) => $s->start_time.'|'.$s->end_time)
            ->take(self::MAX_COLUMNS)
            ->map(fn (Shift $s) => ['start' => substr((string) $s->start_time, 0, 5), 'end' => substr((string) $s->end_time, 0, 5), 'name' => (string) $s->name])
            ->values()
            ->all();
    }

    /**
     * The window of one cell in epoch minutes, half-open.
     *
     * @param  list<array{start: string, end: string, name: string}>  $columns
     * @return array{0: int, 1: int}|null
     */
    public static function window(array $columns, string $date, int $col): ?array
    {
        $column = $columns[$col - 1] ?? null;
        if ($column === null) {
            return null;
        }
        $day = Carbon::parse($date)->startOfDay();
        $start = $day->copy()->setTimeFromTimeString($column['start']);
        $end = $day->copy()->setTimeFromTimeString($column['end']);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [ResourceCalendar::toMinutes($start), ResourceCalendar::toMinutes($end)];
    }

    /**
     * A minute plan the planner set on purpose — not the envelope a drop onto
     * a weekly cell writes (shift start to the midnight after the end date).
     */
    public static function isExactPlan(WorkOrder $order): bool
    {
        if ($order->planned_start_at === null || $order->planned_end_at === null || $order->planned_end_at->lessThanOrEqualTo($order->planned_start_at)) {
            return false;
        }
        if (! $order->shift_number) {
            return true;
        }

        return $order->planned_end_at->format('H:i:s') !== '00:00:00';
    }

    /**
     * The cells a day + shift span covers, as the weekly board draws it.
     *
     * @param  list<array{start: string, end: string, name: string}>  $columns
     * @return list<array{0: string, 1: int}> date, column
     */
    public static function span(array $columns, string $date, ?int $shift, ?string $endDate, ?int $endShift, ?CarbonInterface $plannedStart = null): array
    {
        if ($columns === []) {
            return [];
        }
        $perDay = count($columns);
        if (! $shift && $plannedStart !== null) {
            $hour = (int) $plannedStart->format('G');
            $shift = min($perDay, intdiv($hour, intdiv(24, $perDay)) + 1);
        }
        $shift = min(max(1, (int) ($shift ?: 1)), $perDay);
        $lastDate = $date;
        $lastShift = $shift;
        if ($endDate !== null && $endDate >= $date) {
            $lastDate = $endDate;
            $lastShift = min(max(1, (int) ($endShift ?: $shift)), $perDay);
        } elseif ($endShift && $endShift > $shift) {
            $lastShift = min((int) $endShift, $perDay);
        }
        if ($lastDate === $date && $lastShift < $shift) {
            $lastShift = $shift;
        }

        $cells = [];
        $day = Carbon::parse($date);
        for ($i = 0; $i < 62 && $day->toDateString() <= $lastDate; $i++, $day->addDay()) {
            $d = $day->toDateString();
            $first = $d === $date ? $shift : 1;
            $last = $d === $lastDate ? $lastShift : $perDay;
            for ($col = $first; $col <= $last; $col++) {
                $cells[] = [$d, $col];
            }
        }

        return $cells;
    }

    /**
     * The windows of the cells a dropped order's primary placement covers,
     * or null when the order carries an exact minute plan instead.
     *
     * @param  list<array{start: string, end: string, name: string}>  $columns
     * @return list<array{0: int, 1: int}>|null
     */
    public static function envelopeWindows(array $columns, WorkOrder $order): ?array
    {
        if (self::isExactPlan($order)) {
            return null;
        }
        $date = $order->planned_start_at?->toDateString() ?? $order->due_date?->toDateString();
        if ($date === null) {
            return [];
        }
        $windows = [];
        foreach (self::span($columns, $date, $order->shift_number, $order->end_date?->toDateString(), $order->end_shift_number, $order->planned_start_at) as [$d, $col]) {
            $window = self::window($columns, $d, $col);
            if ($window !== null) {
                $windows[] = $window;
            }
        }

        return $windows;
    }
}
