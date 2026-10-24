<?php

namespace App\Services\Dashboard;

use App\Enums\DowntimeKind;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\WorkOrder;
use App\Models\WorkOrderOutputEntry;
use App\Models\Workstation;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Planned vs actual output of one shift, hour by hour — the read model behind
 * the dashboard's "Production · planned vs actual" and "Output per hour".
 *
 * Plan — the Schedule board draws every order from its own
 * `planned_start_at` / `planned_end_at` / `line_id`. Applying a scheduler
 * simulation writes the envelope of the committed ScheduledOperation rows back
 * onto those same columns, so the order's own window is what the board shows
 * with or without a simulation, and it is what is used here. Operations only
 * spread an order across stations and are never read for quantities; draft
 * simulations are never read at all. An order's `planned_qty` is spread
 * linearly over its window and the part overlapping each hour counts for that
 * hour. Orders placed only by date + shift number (no minute window) carry no
 * hourly plan and are left out, as in ProductionTargets. A DONE order stays in
 * the plan — it was this shift's plan, and dropping it would make the plan line
 * fall when work finishes.
 *
 * Actual — `produced_qty` is a running total written by batches, shift
 * entries, machine counters, operator and supervisor reports and corrections.
 * Every change is logged with its time in `work_order_output_entries`
 * ({@see \App\Observers\WorkOrderOutputObserver}); an hour's output is the sum
 * of the deltas recorded in it, on the lines in scope. There is no backfill:
 * output from before the ledger existed is not in it.
 *
 * No plan is `null`, never 0. Query count is fixed regardless of the number of
 * lines and hours. Times are in the plant timezone (the app timezone).
 */
class ShiftPlanService
{
    /** Statuses whose orders count towards the plan. */
    private const PLAN_STATUSES = [...WorkOrder::ACTIVE_STATUSES, WorkOrder::STATUS_DONE];

    /**
     * @return array<string, mixed>
     */
    public function forShift(ShiftWindow $window, ?int $lineId = null, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now())->copy();
        $start = $window->start->copy();
        $end = $window->end->copy();

        $base = [
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'now' => $now->toIso8601String(),
            'is_live' => $window->contains($now),
            'is_default' => $window->shift === null,
        ];

        $lines = Line::active()
            ->when($lineId, fn ($q) => $q->whereKey($lineId))
            ->get(['id', 'name']);

        if ($lines->isEmpty()) {
            return $base + [
                'has_lines' => false,
                'hours' => [],
                'cumulative' => [],
                'totals' => ['planned' => null, 'actual' => null, 'planned_to_now' => null, 'pace_delta' => null, 'pct_of_plan' => null],
                'plan_per_hour' => null,
                'summary' => ['kind' => 'no_lines', 'since' => null, 'downtime_minutes' => null, 'downtime_lines' => []],
            ];
        }

        $lineIds = $lines->pluck('id')->all();

        // The clock clipped to the shift: everything before it has happened.
        $cut = $now->copy()->max($start)->min($end);
        $started = $now->greaterThanOrEqualTo($start);

        $buckets = $this->buckets($start, $end);
        $orders = $this->plannedOrders($lineIds, $start, $end);

        $planTotal = $this->spread($orders, $start, $end);
        $hasPlan = round($planTotal, 2) > 0;

        $actuals = $started ? $this->actualPerBucket($lineIds, $buckets, $cut) : [];

        $hours = [];
        foreach ($buckets as $i => [$from, $to]) {
            $phase = match (true) {
                $to->lessThanOrEqualTo($now) => 'past',
                $from->lessThanOrEqualTo($now) => 'current',
                default => 'future',
            };

            $hours[] = [
                'start' => $from->toIso8601String(),
                'end' => $to->toIso8601String(),
                'phase' => $phase,
                'planned' => $hasPlan ? round($this->spread($orders, $from, $to), 2) : null,
                'actual' => $phase === 'future' ? null : round($actuals[$i] ?? 0.0, 2),
            ];
        }

        $actualTotal = $started ? round(array_sum($actuals), 2) : null;
        $plannedToNow = $hasPlan ? round($this->spread($orders, $start, $cut), 2) : null;

        return $base + [
            'has_lines' => true,
            'hours' => $hours,
            'cumulative' => $this->cumulative($orders, $hasPlan, $buckets, $hours, $start, $cut, $started),
            'totals' => [
                'planned' => $hasPlan ? round($planTotal, 2) : null,
                'actual' => $actualTotal,
                'planned_to_now' => $plannedToNow,
                'pace_delta' => $hasPlan && $started ? (int) round($actualTotal - $plannedToNow) : null,
                'pct_of_plan' => $hasPlan && $started ? (int) round($actualTotal / $planTotal * 100) : null,
            ],
            'plan_per_hour' => $hasPlan
                ? round($planTotal / (($end->getTimestamp() - $start->getTimestamp()) / 3600), 2)
                : null,
            'summary' => $this->summary($hasPlan, $hours, $lines, $cut),
        ];
    }

    /**
     * Hour buckets from the shift start, stepping 3600 s of real time (so a 05:30
     * shift gets 05:30, 06:30, … and a DST night stays correct); the last one is
     * clipped to the shift end.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function buckets(Carbon $start, Carbon $end): array
    {
        $buckets = [];
        $from = $start->copy();
        while ($from->lessThan($end)) {
            $to = $from->copy()->addSeconds(3600)->min($end);
            $buckets[] = [$from, $to];
            $from = $to->copy();
        }

        return $buckets;
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return Collection<int, WorkOrder>
     */
    private function plannedOrders(array $lineIds, Carbon $start, Carbon $end): Collection
    {
        return WorkOrder::whereIn('line_id', $lineIds)
            ->whereIn('status', self::PLAN_STATUSES)
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->where('planned_start_at', '<', $end)
            ->where('planned_end_at', '>', $start)
            ->where('planned_qty', '>', 0)
            ->get(['id', 'line_id', 'planned_qty', 'planned_start_at', 'planned_end_at'])
            ->filter(fn (WorkOrder $o) => $o->planned_end_at->greaterThan($o->planned_start_at))
            ->values();
    }

    /**
     * Planned pieces falling in [from, to): each order's quantity spread
     * linearly over its own window.
     *
     * @param  Collection<int, WorkOrder>  $orders
     */
    private function spread(Collection $orders, Carbon $from, Carbon $to): float
    {
        $a = $from->getTimestamp();
        $b = $to->getTimestamp();
        if ($b <= $a) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($orders as $order) {
            $s = $order->planned_start_at->getTimestamp();
            $e = $order->planned_end_at->getTimestamp();
            $overlap = min($e, $b) - max($s, $a);
            if ($overlap > 0) {
                $sum += (float) $order->planned_qty * $overlap / ($e - $s);
            }
        }

        return $sum;
    }

    /**
     * Output recorded in each hour that has started by `$cut`, in one aggregate query. The
     * CASE bounds are bound Carbon values — no user input reaches the SQL.
     *
     * @param  array<int, int>  $lineIds
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $buckets
     * @return array<int, float>
     */
    private function actualPerBucket(array $lineIds, array $buckets, Carbon $cut): array
    {
        $columns = [];
        $bindings = [];
        $until = $buckets[0][1];
        foreach ($buckets as $i => [$from, $to]) {
            // Hours that have started; nothing is recorded after now, so the
            // current one needs no upper bound tighter than its own end.
            if ($from->greaterThan($cut)) {
                break;
            }
            $columns[] = "COALESCE(SUM(CASE WHEN recorded_at >= ? AND recorded_at < ? THEN delta ELSE 0 END), 0) AS h{$i}";
            $bindings[] = $from;
            $bindings[] = $to;
            $until = $to;
        }

        $row = WorkOrderOutputEntry::query()
            ->whereIn('line_id', $lineIds)
            ->where('recorded_at', '>=', $buckets[0][0])
            ->where('recorded_at', '<', $until)
            // A deleted order's output is gone from the order too.
            ->whereHas('workOrder')
            ->selectRaw(implode(', ', $columns), $bindings)
            ->toBase()
            ->first();

        $result = [];
        foreach (array_keys($columns) as $i) {
            $result[$i] = (float) ($row->{"h{$i}"} ?? 0);
        }

        return $result;
    }

    /**
     * Running totals at the shift start, every hour boundary and now. Actual is
     * null after now; plan covers the whole shift.
     *
     * @param  Collection<int, WorkOrder>  $orders
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $buckets
     * @param  array<int, array<string, mixed>>  $hours
     * @return array<int, array{at: string, planned: ?float, actual: ?float}>
     */
    private function cumulative(Collection $orders, bool $hasPlan, array $buckets, array $hours, Carbon $start, Carbon $cut, bool $started): array
    {
        $point = fn (Carbon $at, ?float $actual) => [
            'at' => $at->toIso8601String(),
            'planned' => $hasPlan ? round($this->spread($orders, $start, $at), 2) : null,
            'actual' => $actual === null ? null : round($actual, 2),
        ];

        $points = [$point($start, $started ? 0.0 : null)];
        $running = 0.0;

        foreach ($buckets as $i => [$from, $to]) {
            if ($started && $from->lessThan($cut) && $to->greaterThan($cut)) {
                $points[] = $point($cut, $running + (float) $hours[$i]['actual']);
            }
            $done = $started && $to->lessThanOrEqualTo($cut);
            if ($done) {
                $running += (float) $hours[$i]['actual'];
            }
            $points[] = $point($to, $done ? $running : null);
        }

        return $points;
    }

    /**
     * What the hours say, for one sentence under the bars: the run of completed
     * hours, ending with the last one, that were all below (or all above) plan,
     * and the unplanned downtime overlapping a below-plan run.
     *
     * @param  array<int, array<string, mixed>>  $hours
     * @param  Collection<int, Line>  $lines
     * @return array{kind: string, since: ?string, downtime_minutes: ?int, downtime_lines: array<int, string>}
     */
    private function summary(bool $hasPlan, array $hours, Collection $lines, Carbon $cut): array
    {
        $result = ['kind' => 'no_plan', 'since' => null, 'downtime_minutes' => null, 'downtime_lines' => []];
        if (! $hasPlan) {
            return $result;
        }

        $past = array_values(array_filter($hours, fn ($h) => $h['phase'] === 'past'));
        if ($past === []) {
            return ['kind' => 'not_started'] + $result;
        }

        $sign = fn (array $h) => round($h['actual']) <=> round($h['planned']);
        $last = $sign(end($past));
        if ($last === 0) {
            return ['kind' => 'on'] + $result;
        }

        $since = null;
        for ($i = count($past) - 1; $i >= 0 && $sign($past[$i]) === $last; $i--) {
            $since = $past[$i]['start'];
        }

        $result['kind'] = $last < 0 ? 'below' : 'above';
        $result['since'] = $since;

        if ($last < 0) {
            [$minutes, $names] = $this->unplannedDowntime($lines, Carbon::parse($since), $cut);
            $result['downtime_minutes'] = $minutes > 0 ? $minutes : null;
            $result['downtime_lines'] = $names;
        }

        return $result;
    }

    /**
     * Unplanned (or not yet classified) stops overlapping [from, to) on the
     * lines: minutes per line with overlapping stops merged, summed, and the
     * names of up to three lines that lost the most.
     *
     * @param  Collection<int, Line>  $lines
     * @return array{0: int, 1: array<int, string>}
     */
    private function unplannedDowntime(Collection $lines, Carbon $from, Carbon $to): array
    {
        if ($to->lessThanOrEqualTo($from)) {
            return [0, []];
        }

        $lineIds = $lines->pluck('id');
        $stationLine = Workstation::whereIn('line_id', $lineIds)->pluck('line_id', 'id');

        $stops = ProductionDowntime::with('reason')
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stationLine->keys()))
            ->where('started_at', '<', $to)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $from))
            ->get();

        $intervals = [];
        foreach ($stops as $stop) {
            $kind = $stop->reason?->kind;
            if ($kind !== null && $kind !== DowntimeKind::Unplanned && ! $stop->needs_reason) {
                continue;
            }
            $line = ($stop->workstation_id ? $stationLine->get($stop->workstation_id) : null) ?? $stop->line_id;
            if (! $line) {
                continue;
            }
            $s = max($stop->started_at->getTimestamp(), $from->getTimestamp());
            $e = min(($stop->ended_at ?? $to)->getTimestamp(), $to->getTimestamp());
            if ($e > $s) {
                $intervals[$line][] = [$s, $e];
            }
        }

        $seconds = [];
        foreach ($intervals as $line => $list) {
            usort($list, fn ($x, $y) => $x[0] <=> $y[0]);
            $total = 0;
            $current = null;
            foreach ($list as [$s, $e]) {
                if ($current === null || $s > $current[1]) {
                    $total += $current ? $current[1] - $current[0] : 0;
                    $current = [$s, $e];
                } else {
                    $current[1] = max($current[1], $e);
                }
            }
            $seconds[$line] = $total + ($current ? $current[1] - $current[0] : 0);
        }

        arsort($seconds);
        $names = $lines->pluck('name', 'id');

        return [
            (int) round(array_sum($seconds) / 60),
            array_values(array_map(fn ($id) => (string) $names->get($id), array_slice(array_keys($seconds), 0, 3))),
        ];
    }
}
