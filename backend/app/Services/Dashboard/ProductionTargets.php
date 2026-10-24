<?php

namespace App\Services\Dashboard;

use App\Enums\ScheduledOperationStatus;
use App\Models\Line;
use App\Models\ScheduledOperation;
use App\Models\WorkOrder;
use App\Support\OeeBand;
use App\Support\ShiftWindow;
use Carbon\Carbon;

/**
 * The OEE a line — and the plant — is expected to reach in a shift.
 *
 * There is no separate OEE target setting: a line's `planning_efficiency_pct`
 * is the share of calendar capacity the planner may load, i.e. the efficiency
 * the schedule already assumes the line runs at. Holding the line to that same
 * figure keeps the plan and the target from contradicting each other.
 *
 * 100 % is the column's default and means "never set" — nobody plans a line
 * at full calendar capacity on purpose — so it falls back to the plant-wide
 * green threshold ({@see OeeBand::GREEN_AT_LEAST}). A planner who genuinely
 * means 100 % therefore gets 85 %; that is the documented trade-off.
 */
class ProductionTargets
{
    public function oeeTargetForLine(Line $line): float
    {
        $pct = $line->planning_efficiency_pct;

        return $pct !== null && $pct >= 1 && $pct < 100 ? (float) $pct : OeeBand::GREEN_AT_LEAST;
    }

    public function plantOeeTarget(ShiftWindow $window): float
    {
        return $this->plantOeeTargetBetween($window->start, $window->end);
    }

    /**
     * Line targets averaged by how long each line is planned to run in the
     * window. Lines with nothing planned carry no weight; with nothing planned
     * anywhere there is nothing to weigh, so the plant default applies.
     */
    public function plantOeeTargetBetween(Carbon $from, Carbon $to): float
    {
        $minutes = array_filter($this->plannedMinutesByLine($from, $to));
        if ($minutes === []) {
            return OeeBand::GREEN_AT_LEAST;
        }

        $lines = Line::where('is_active', true)->whereIn('id', array_keys($minutes))->get();

        $weighted = 0.0;
        $total = 0;
        foreach ($lines as $line) {
            $weighted += $this->oeeTargetForLine($line) * $minutes[$line->id];
            $total += $minutes[$line->id];
        }

        return $total > 0 ? $weighted / $total : OeeBand::GREEN_AT_LEAST;
    }

    /**
     * Minutes of [from, to) each line has planned work in, from the committed
     * schedule.
     *
     * An order's load sits with its scheduled operations when it has any, and
     * with its own planned start/end otherwise — the same rule the capacity
     * grid uses, so an order is never counted twice. Intervals are clipped to
     * the window and merged, so operations running side by side on parallel
     * workstations count once: the weight is time the line is busy, at most
     * the window's length.
     *
     * @return array<int, int> line id => minutes
     */
    public function plannedMinutesByLine(Carbon $from, Carbon $to): array
    {
        $intervals = [];

        $operations = ScheduledOperation::committed()
            ->whereIn('status', [ScheduledOperationStatus::Scheduled->value, ScheduledOperationStatus::ScheduledLate->value])
            ->whereNotNull('line_id')
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->where('planned_start_at', '<', $to)
            ->where('planned_end_at', '>', $from)
            // A deleted order's leftover plan is not work anyone will run.
            ->whereHas('workOrder')
            ->get(['line_id', 'planned_start_at', 'planned_end_at']);

        foreach ($operations as $op) {
            $intervals[$op->line_id][] = [$op->planned_start_at, $op->planned_end_at];
        }

        $orders = WorkOrder::whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->whereNotNull('line_id')
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->where('planned_start_at', '<', $to)
            ->where('planned_end_at', '>', $from)
            ->whereNotExists(fn ($q) => $q->select('id')
                ->from('scheduled_operations')
                ->whereColumn('scheduled_operations.work_order_id', 'work_orders.id')
                ->whereNull('scheduled_operations.schedule_simulation_id'))
            ->get(['id', 'line_id', 'planned_start_at', 'planned_end_at']);

        foreach ($orders as $order) {
            $intervals[$order->line_id][] = [$order->planned_start_at, $order->planned_end_at];
        }

        $result = [];
        foreach ($intervals as $lineId => $list) {
            $result[(int) $lineId] = $this->mergedMinutes($list, $from, $to);
        }

        return $result;
    }

    /**
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $intervals
     */
    private function mergedMinutes(array $intervals, Carbon $from, Carbon $to): int
    {
        $clipped = [];
        foreach ($intervals as [$start, $end]) {
            $s = max($start->getTimestamp(), $from->getTimestamp());
            $e = min($end->getTimestamp(), $to->getTimestamp());
            if ($e > $s) {
                $clipped[] = [$s, $e];
            }
        }

        usort($clipped, fn ($a, $b) => $a[0] <=> $b[0]);

        $seconds = 0;
        $current = null;
        foreach ($clipped as [$s, $e]) {
            if ($current === null || $s > $current[1]) {
                if ($current !== null) {
                    $seconds += $current[1] - $current[0];
                }
                $current = [$s, $e];
            } else {
                $current[1] = max($current[1], $e);
            }
        }
        if ($current !== null) {
            $seconds += $current[1] - $current[0];
        }

        return intdiv($seconds, 60);
    }
}
