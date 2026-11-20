<?php

namespace App\Services\Schedule;

use App\Enums\ScheduledOperationStatus;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\ScheduledOperation;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Schedule\Finite\ResourceCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes the schedule-capacity grid: how many hours each resource has
 * available (from its shift calendar) versus how many hours of work are
 * planned against it, per time bucket (day or week).
 *
 * The *line* axis ({@see lineCapacity()}) supplies machine capacity: calendar
 * hours from active shifts (line-specific plus global) minus scheduled
 * maintenance, derated by the line's planning efficiency into the effective
 * (available) hours that load is measured against; planned hours from active work orders (an order the
 * finite scheduler placed counts by its committed operations, each on the line
 * of its station; other minute-plans are distributed across buckets, else a
 * best-effort estimate lumped on the placement date; unestimated orders are
 * counted separately rather than treated as zero load).
 *
 * The *crew* axis ({@see crewCapacity()}) supplies labor capacity: available
 * hours from each crew's workers (their line's shifts minus approved absences
 * and crew break windows; a shift's minutes count on the date it starts and
 * absences are checked on that date, so a night shift is the evening's — the
 * same convention the finite scheduler uses), planned hours from the forward
 * labor demand of the lines the crew staffs (explicit crew↔line assignment,
 * else derived from worker → workstation → line), weighted by each order's
 * required operators.
 * See {@see crewCapacity()} for the demand model.
 *
 * Every cell carries both figures: `calendar_h` (what the shift calendar
 * offers) and `available_h` (what planning may load after the efficiency
 * factor). With the default 100 % they are equal, so existing installations
 * see exactly the numbers they always did.
 */
class CapacityService
{
    public function __construct(private WorkforceProvider $availability) {}

    /**
     * Build the line-axis capacity grid for the given range and granularity.
     *
     * @return array{granularity: string, buckets: array<int, array>, resources: array<int, array>}
     */
    public function lineCapacity(Carbon $rangeStart, Carbon $rangeEnd, string $granularity = 'week'): array
    {
        $granularity = in_array($granularity, ['day', 'week'], true) ? $granularity : 'week';

        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEnd = $rangeEnd->copy()->endOfDay();

        $buckets = $this->buildBuckets($rangeStart, $rangeEnd, $granularity);

        $lines = Line::where('is_active', true)->orderBy('name')->get();
        $shiftIndex = $this->buildShiftIndex(Shift::where('is_active', true)->get());

        $workOrders = $this->activeWorkOrdersInRange($rangeStart, $rangeEnd);
        $opsByOrder = $this->committedOperationsByOrder($workOrders);
        $resourceCalendar = ResourceCalendar::forHorizon($rangeStart, $rangeEnd);

        $maintenance = MaintenanceEvent::whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->whereNotNull('line_id')
            ->where('scheduled_at', '>=', $rangeStart)
            ->where('scheduled_at', '<=', $rangeEnd)
            ->get();

        $resources = [];
        foreach ($lines as $line) {
            $lineMaint = $maintenance->where('line_id', $line->id);

            $factor = $line->planningEfficiencyFactor();

            $cells = [];
            foreach ($buckets as $bucket) {
                $calendar = max(0, $this->lineAvailableMinutes($shiftIndex, $line->id, $bucket, $rangeStart, $rangeEnd)
                    - $this->maintenanceMinutes($lineMaint, $bucket));

                $cells[$bucket['key']] = [
                    'calendar_minutes' => $calendar,
                    'available_minutes' => $calendar * $factor,
                    'planned_minutes' => 0,
                    'unestimated_count' => 0,
                ];
            }

            foreach ($workOrders as $wo) {
                $this->placeOrder($this->lineContributions($wo, $line->id, $buckets, $opsByOrder, $resourceCalendar, $factor), $cells);
            }

            $resources[] = [
                'id' => $line->id,
                'name' => $line->name,
                'code' => $line->code,
                'planning_efficiency_pct' => round($factor * 100, 2),
                'cells' => $this->finalizeCells($cells),
            ];
        }

        return $this->grid($granularity, $buckets, $resources);
    }

    /**
     * Build the crew-axis (labor) capacity grid.
     *
     * Available = the labor-hours each crew supplies per bucket (its workers'
     * line shifts minus approved absences and crew break windows).
     *
     * Planned = the forward labor demand of the lines the crew staffs. A crew
     * "staffs" the lines its explicit assignment lists (or, failing that, the
     * lines its active workers' workstations belong to). Each such line's
     * planned machine-hours are weighted into person-hours by its orders'
     * duration-weighted operator count ({@see WorkOrder::operatorFactor()}),
     * then split equally among the crews that staff the line so shared lines
     * aren't double-counted. Demand on lines no crew staffs is surfaced in an
     * "Unassigned" row (zero available, so it reads as over-capacity).
     *
     * @return array{granularity: string, buckets: array<int, array>, resources: array<int, array>}
     */
    public function crewCapacity(Carbon $rangeStart, Carbon $rangeEnd, string $granularity = 'week'): array
    {
        $granularity = in_array($granularity, ['day', 'week'], true) ? $granularity : 'week';

        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEnd = $rangeEnd->copy()->endOfDay();

        $buckets = $this->buildBuckets($rangeStart, $rangeEnd, $granularity);

        // Crews are workforce administration, and not every installation records
        // them. Asking the contract first keeps the model out of the picture
        // entirely when there are none — but the rest of this method still runs,
        // because demand on a line nobody staffs is exactly what the Unassigned
        // row exists to show.
        $crews = $this->availability->crewsForCapacity();

        $shiftIndex = $this->buildShiftIndex(Shift::where('is_active', true)->get());

        // Operator-weighted labor demand per line per bucket. Every segment an
        // order runs demands labor on its line, each on its own dates.
        // Each order's machine-minutes on a line × its duration-weighted
        // operator count ({@see WorkOrder::operatorFactor()}) — the person-minutes
        // the line's planned work needs. Kept as floats and rounded once at the
        // hour-display step, so per-order rounding can't drift the bucket total.
        $workOrders = $this->activeWorkOrdersInRange($rangeStart, $rangeEnd);
        $opsByOrder = $this->committedOperationsByOrder($workOrders);
        $resourceCalendar = ResourceCalendar::forHorizon($rangeStart, $rangeEnd);
        $lineFactors = Line::all()->mapWithKeys(fn (Line $line) => [$line->id => $line->planningEfficiencyFactor()]);
        $lineDemand = [];
        foreach ($workOrders as $wo) {
            $lineIds = array_unique(array_filter([
                $wo->line_id,
                ...$wo->extraPlacements->pluck('line_id')->all(),
                ...$opsByOrder->get($wo->id, collect())->pluck('line_id')->all(),
            ]));
            $operators = $wo->operatorFactor();
            foreach ($lineIds as $lineId) {
                $contribution = $this->lineContributions($wo, (int) $lineId, $buckets, $opsByOrder, $resourceCalendar, $lineFactors[$lineId] ?? 1.0);
                foreach ($contribution['minutes'] as $key => $minutes) {
                    $lineDemand[$lineId][$key] = ($lineDemand[$lineId][$key] ?? 0.0) + $minutes * $operators;
                }
            }
        }

        // Lines each crew staffs (resolved once per crew) and how many crews
        // staff each line, to split a line's demand fairly.
        $crewLineIdsById = [];
        $crewsPerLine = [];
        foreach ($crews as $crew) {
            $crewLineIdsById[$crew->id] = $this->crewLineIds($crew);
            foreach ($crewLineIdsById[$crew->id] as $lineId) {
                $crewsPerLine[$lineId] = ($crewsPerLine[$lineId] ?? 0) + 1;
            }
        }

        // Absent-worker IDs are resolved once per distinct day and reused.
        $absentByDay = [];

        $resources = [];
        foreach ($crews as $crew) {
            $factor = $this->crewEfficiencyFactor($crew);
            $cells = [];
            foreach ($buckets as $bucket) {
                // Float through the split; round once when converting to hours.
                $planned = 0.0;
                foreach ($crewLineIdsById[$crew->id] as $lineId) {
                    $lineMinutes = $lineDemand[$lineId][$bucket['key']] ?? 0;
                    $planned += $lineMinutes / max(1, $crewsPerLine[$lineId] ?? 1);
                }

                $calendar = $this->crewAvailableMinutes($crew, $shiftIndex, $bucket, $rangeStart, $rangeEnd, $absentByDay);
                $cells[$bucket['key']] = [
                    'calendar_minutes' => $calendar,
                    'available_minutes' => $calendar * $factor,
                    'planned_minutes' => $planned,
                    'unestimated_count' => 0,
                ];
            }

            $resources[] = [
                'id' => $crew->id,
                'name' => $crew->name,
                'code' => $crew->code,
                'planning_efficiency_pct' => round($factor * 100, 2),
                'cells' => $this->finalizeCells($cells),
            ];
        }

        // Labor demand on lines no crew staffs would otherwise vanish; surface
        // it in an "Unassigned" row with zero available labor (reads as over
        // capacity) so the planner sees unmet demand instead of idle crews.
        $unassigned = [];
        $hasUnassigned = false;
        foreach ($buckets as $bucket) {
            $minutes = 0;
            foreach ($lineDemand as $lineId => $perBucket) {
                if (($crewsPerLine[$lineId] ?? 0) === 0) {
                    $minutes += $perBucket[$bucket['key']] ?? 0;
                }
            }
            $unassigned[$bucket['key']] = ['calendar_minutes' => 0, 'available_minutes' => 0, 'planned_minutes' => $minutes, 'unestimated_count' => 0];
            $hasUnassigned = $hasUnassigned || $minutes > 0;
        }
        if ($hasUnassigned) {
            $resources[] = [
                'id' => 0,
                'name' => __('Unassigned'),
                'code' => null,
                'planning_efficiency_pct' => 100.0,
                'cells' => $this->finalizeCells($unassigned),
            ];
        }

        return $this->grid($granularity, $buckets, $resources);
    }

    /**
     * Build the workstation-axis grid from the committed operation plan.
     *
     * Available = the station's working time in the bucket (its line's shifts
     * minus line and station maintenance, {@see ResourceCalendar}) × the line's
     * planning efficiency. Planned = the scheduled operations committed on the
     * station, as standard work: the working time they occupy × the same
     * efficiency, since the scheduler stretched them by its inverse. Operations
     * exist only for orders placed by the finite-capacity scheduler, so a
     * station whose work was only ever placed by hand reads as idle here — the
     * line axis is where that work shows.
     *
     * @return array{granularity: string, buckets: array<int, array>, resources: array<int, array>}
     */
    public function workstationCapacity(Carbon $rangeStart, Carbon $rangeEnd, string $granularity = 'week'): array
    {
        $granularity = in_array($granularity, ['day', 'week'], true) ? $granularity : 'week';

        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEnd = $rangeEnd->copy()->endOfDay();
        $buckets = $this->buildBuckets($rangeStart, $rangeEnd, $granularity);

        $lines = Line::where('is_active', true)->get()->keyBy('id');
        $stations = Workstation::with('line:id,name')
            ->where('is_active', true)
            ->whereIn('line_id', $lines->keys())
            ->get()
            ->sortBy(fn (Workstation $w) => [$w->line?->name, $w->name])
            ->values();

        $calendar = ResourceCalendar::forHorizon($rangeStart, $rangeEnd);
        $operations = ScheduledOperation::committed()
            ->whereIn('workstation_id', $stations->pluck('id'))
            ->whereIn('status', [ScheduledOperationStatus::Scheduled->value, ScheduledOperationStatus::ScheduledLate->value])
            ->whereHas('workOrder', fn ($q) => $q->whereIn('status', WorkOrder::ACTIVE_STATUSES))
            ->where('planned_start_at', '<', $rangeEnd)
            ->where('planned_end_at', '>', $rangeStart)
            ->get()
            ->groupBy('workstation_id');

        $resources = [];
        foreach ($stations as $station) {
            $factor = $lines->get($station->line_id)->planningEfficiencyFactor();
            $windows = $calendar->windows((int) $station->line_id, $station->id);

            $cells = [];
            foreach ($buckets as $bucket) {
                $from = ResourceCalendar::toMinutes($bucket['start']);
                $to = ResourceCalendar::toMinutes($bucket['end']) + 1;
                $calendarMinutes = ResourceCalendar::workingMinutesBetween($windows, $from, $to);

                $occupied = 0;
                foreach ($operations->get($station->id, collect()) as $op) {
                    $occupied += ResourceCalendar::workingMinutesBetween(
                        $windows,
                        max($from, ResourceCalendar::toMinutes($op->planned_start_at)),
                        min($to, ResourceCalendar::toMinutes($op->planned_end_at)),
                    );
                }

                $cells[$bucket['key']] = [
                    'calendar_minutes' => $calendarMinutes,
                    'available_minutes' => $calendarMinutes * $factor,
                    'planned_minutes' => $occupied * $factor,
                    'unestimated_count' => 0,
                ];
            }

            $resources[] = [
                'id' => $station->id,
                'name' => $station->name,
                'code' => $station->line?->name,
                'planning_efficiency_pct' => round($factor * 100, 2),
                'cells' => $this->finalizeCells($cells),
            ];
        }

        return $this->grid($granularity, $buckets, $resources);
    }

    /**
     * Work orders contributing to a single line-axis cell (for the drill-down),
     * each with the hours it contributes to that bucket. Uses the same
     * {@see lineContributions()} as the grid, so the listed orders always
     * reconcile with the cell total.
     *
     * @return array<int, array>
     */
    public function cellOrders(int $lineId, Carbon $bucketStart, Carbon $bucketEnd): array
    {
        $bucket = [
            'key' => 'cell',
            'start' => $bucketStart->copy()->startOfDay(),
            'end' => $bucketEnd->copy()->endOfDay(),
        ];

        $orders = WorkOrder::with(['batches.steps', 'productType', 'extraPlacements'])
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->where(function ($q) use ($lineId) {
                $q->where('line_id', $lineId)
                    ->orWhereHas('extraPlacements', fn ($q2) => $q2->where('line_id', $lineId))
                    ->orWhereIn('id', ScheduledOperation::committed()->where('line_id', $lineId)->select('work_order_id'));
            })
            ->where(function ($q) use ($bucket) {
                $q->whereBetween('due_date', [$bucket['start'], $bucket['end']])
                    ->orWhereBetween('planned_start_at', [$bucket['start'], $bucket['end']])
                    ->orWhereHas('extraPlacements', fn ($q2) => $q2->whereBetween('due_date', [$bucket['start'], $bucket['end']]))
                    ->orWhere(function ($q2) use ($bucket) {
                        $q2->whereNotNull('planned_start_at')
                            ->whereNotNull('planned_end_at')
                            ->where('planned_start_at', '<', $bucket['end'])
                            ->where('planned_end_at', '>', $bucket['start']);
                    })
                    ->orWhereNull('due_date');
            })
            ->orderBy('priority', 'desc')
            ->orderBy('due_date')
            ->get();

        $opsByOrder = $this->committedOperationsByOrder($orders);
        $calendar = ResourceCalendar::forHorizon($bucket['start'], $bucket['end']);
        $factor = Line::find($lineId)?->planningEfficiencyFactor() ?? 1.0;

        $rows = [];
        foreach ($orders as $raw) {
            // Every segment and operation the order runs on this line within the bucket.
            $contribution = $this->lineContributions($raw, $lineId, [$bucket], $opsByOrder, $calendar, $factor);
            $minutes = $contribution['minutes'][$bucket['key']] ?? 0;
            $unestimated = in_array($bucket['key'], $contribution['unestimatedKeys'], true);
            $projections = $this->projectionsForLine($raw, $lineId);
            $wo = $projections === [] ? $raw : end($projections);
            if ($minutes <= 0 && ! $unestimated) {
                continue;
            }

            $rows[] = [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'product_name' => $wo->productType?->name,
                'status' => $wo->status,
                'line_id' => $wo->line_id,
                'due_date' => $wo->due_date?->toDateString(),
                'week_number' => $wo->week_number,
                'shift_number' => $wo->shift_number,
                'planned_start_at' => $wo->planned_start_at?->toIso8601String(),
                'planned_end_at' => $wo->planned_end_at?->toIso8601String(),
                'hours' => round($minutes / 60, 1),
                'unestimated' => $unestimated,
                'minute_planned' => $wo->hasMinutePlanning(),
            ];
        }

        return $rows;
    }

    /**
     * Assemble the public grid payload (buckets serialized to date strings).
     *
     * @return array{granularity: string, buckets: array<int, array>, resources: array<int, array>}
     */
    private function grid(string $granularity, array $buckets, array $resources): array
    {
        return [
            'granularity' => $granularity,
            'buckets' => array_map(fn ($b) => [
                'key' => $b['key'],
                'label' => $b['label'],
                'start' => $b['start']->toDateString(),
                'end' => $b['end']->toDateString(),
            ], $buckets),
            'resources' => $resources,
        ];
    }

    /**
     * How a work order's planned hours spread across the buckets — computed
     * once per order (bucket placement is resolved a single time, not per
     * bucket). The single source of truth for both axes and the drill-down.
     *
     * @return array{minutes: array<string, int>, unestimatedKey: ?string}
     *                                                                     minutes: bucket key => planned minutes;
     *                                                                     unestimatedKey: the bucket an unestimable order is flagged in
     */
    private function orderContributions(WorkOrder $wo, array $buckets): array
    {
        $minutes = [];

        if ($wo->hasMinutePlanning()) {
            // An inverted/zero span (planned_end <= planned_start) is invalid but the
            // data model permits it (plannedDurationMinutes() returns it unclamped).
            // Surface it as unestimated in the bucket holding planned_start_at rather
            // than silently dropping it (a hidden double-book).
            if ($wo->planned_end_at->lessThanOrEqualTo($wo->planned_start_at)) {
                foreach ($buckets as $bucket) {
                    if ($wo->planned_start_at->between($bucket['start'], $bucket['end'])) {
                        return ['minutes' => $minutes, 'unestimatedKey' => $bucket['key']];
                    }
                }

                return ['minutes' => $minutes, 'unestimatedKey' => null];
            }

            foreach ($buckets as $bucket) {
                $overlap = $this->overlapMinutes($wo->planned_start_at, $wo->planned_end_at, $bucket);
                if ($overlap > 0) {
                    $minutes[$bucket['key']] = $overlap;
                }
            }

            return ['minutes' => $minutes, 'unestimatedKey' => null];
        }

        // Non-minute-planned orders belong to exactly one bucket (their placement
        // date / week), resolved once here rather than per bucket.
        $key = $this->bucketKeyForOrder($wo, $buckets);
        if ($key === null) {
            return ['minutes' => $minutes, 'unestimatedKey' => null];
        }

        $estimate = $wo->estimatedDurationMinutes();
        if ($estimate === null) {
            return ['minutes' => $minutes, 'unestimatedKey' => $key];
        }

        $minutes[$key] = $estimate;

        return ['minutes' => $minutes, 'unestimatedKey' => null];
    }

    /**
     * The work order's segments on one line, as schedulable proxies: the
     * order itself when its primary placement is on the line, plus a detached
     * copy per extra segment there. Extra segments are coarse-only — the
     * minute plan belongs to the primary placement and never follows a copy.
     *
     * @return array<int, WorkOrder>
     */
    private function projectionsForLine(WorkOrder $wo, int $lineId): array
    {
        $out = [];
        if ($wo->line_id === $lineId) {
            $out[] = $wo;
        }
        foreach ($wo->extraPlacements as $p) {
            if ($p->line_id !== $lineId) {
                continue;
            }
            $proxy = clone $wo;
            $proxy->due_date = $p->due_date;
            $proxy->shift_number = $p->shift_number ?? $wo->shift_number;
            $proxy->end_date = $p->end_date;
            $proxy->end_shift_number = $p->end_shift_number;
            $proxy->planned_start_at = null;
            $proxy->planned_end_at = null;
            $out[] = $proxy;
        }

        return $out;
    }

    /**
     * Committed, placed operations of the given orders, grouped by order. An
     * order that has any carries its load in these rows — each step on the line
     * of the station that runs it — instead of in its overall time window, which
     * after automatic scheduling also spans steps on other lines and the waits
     * between them. This is the same rule the finite scheduler uses when it
     * books existing work, so both screens agree on what a line carries.
     *
     * @return Collection<int, Collection<int, ScheduledOperation>>
     */
    private function committedOperationsByOrder(Collection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        return ScheduledOperation::committed()
            ->whereIn('work_order_id', $orders->pluck('id'))
            ->whereIn('status', [ScheduledOperationStatus::Scheduled->value, ScheduledOperationStatus::ScheduledLate->value])
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->get()
            ->groupBy('work_order_id');
    }

    /**
     * A work order's planned minutes on one line, per bucket: its committed
     * operations on that line as standard work (working time occupied × the
     * line's efficiency, as on the workstation axis), else its placements on
     * the line ({@see orderContributions()}). Extra segments are hand-placed
     * and coarse, so they count either way.
     *
     * @return array{minutes: array<string, float|int>, unestimatedKeys: list<string>}
     */
    private function lineContributions(WorkOrder $wo, int $lineId, array $buckets, Collection $opsByOrder, ResourceCalendar $calendar, float $factor): array
    {
        $ops = $opsByOrder->get($wo->id);
        $minutes = [];
        $unestimatedKeys = [];

        foreach ($this->projectionsForLine($wo, $lineId) as $projection) {
            // Deliberately per order, not per line: once an order has committed
            // operations its primary window counts nowhere, even on a line none
            // of them runs on. Its load is wherever its operations are.
            if ($ops !== null && $projection === $wo) {
                continue;
            }
            $contribution = $this->orderContributions($projection, $buckets);
            foreach ($contribution['minutes'] as $key => $m) {
                $minutes[$key] = ($minutes[$key] ?? 0) + $m;
            }
            if ($contribution['unestimatedKey'] !== null) {
                $unestimatedKeys[] = $contribution['unestimatedKey'];
            }
        }

        foreach ($ops?->where('line_id', $lineId) ?? [] as $op) {
            $windows = $calendar->windows($lineId, $op->workstation_id);
            foreach ($buckets as $bucket) {
                $worked = ResourceCalendar::workingMinutesBetween(
                    $windows,
                    max(ResourceCalendar::toMinutes($bucket['start']), ResourceCalendar::toMinutes($op->planned_start_at)),
                    min(ResourceCalendar::toMinutes($bucket['end']) + 1, ResourceCalendar::toMinutes($op->planned_end_at)),
                );
                if ($worked > 0) {
                    $minutes[$bucket['key']] = ($minutes[$bucket['key']] ?? 0) + $worked * $factor;
                }
            }
        }

        return ['minutes' => $minutes, 'unestimatedKeys' => $unestimatedKeys];
    }

    /**
     * Add one order's contribution to a resource's cells.
     *
     * @param  array{minutes: array<string, float|int>, unestimatedKeys: list<string>}  $contribution
     */
    private function placeOrder(array $contribution, array &$cells): void
    {
        foreach ($contribution['minutes'] as $key => $minutes) {
            $cells[$key]['planned_minutes'] += $minutes;
        }
        foreach ($contribution['unestimatedKeys'] as $key) {
            $cells[$key]['unestimated_count']++;
        }
    }

    /**
     * Active, line-assigned work orders that fall in the visible range — the
     * shared source of planned hours for both the line and crew axes.
     */
    private function activeWorkOrdersInRange(Carbon $rangeStart, Carbon $rangeEnd): \Illuminate\Database\Eloquent\Collection
    {
        return WorkOrder::with(['batches.steps', 'extraPlacements'])
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->whereNotNull('line_id')
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('due_date', [$rangeStart, $rangeEnd])
                    ->orWhereBetween('planned_start_at', [$rangeStart, $rangeEnd])
                    ->orWhere(fn ($span) => $span->where('planned_start_at', '<=', $rangeEnd)->where('end_date', '>=', $rangeStart))
                    // Independently-scheduled extra segments count too.
                    ->orWhereHas('extraPlacements', fn ($q2) => $q2->whereBetween('due_date', [$rangeStart, $rangeEnd]))
                    ->orWhere(function ($q2) use ($rangeStart, $rangeEnd) {
                        // Minute-planned orders overlapping the visible range.
                        $q2->whereNotNull('planned_start_at')
                            ->whereNotNull('planned_end_at')
                            ->where('planned_start_at', '<', $rangeEnd)
                            ->where('planned_end_at', '>', $rangeStart);
                    })
                    ->orWhereNull('due_date'); // week-only orders
            })
            ->get();
    }

    /**
     * Distinct line IDs a crew staffs. Prefers the explicit crew↔line
     * assignment; falls back to the lines the crew's active workers are
     * stationed on when no explicit assignment exists.
     *
     * @return array<int, int>
     */
    private function crewLineIds(object $crew): array
    {
        if ($crew->relationLoaded('lines') && $crew->lines->isNotEmpty()) {
            return $crew->lines->pluck('id')->all();
        }

        return $crew->workers
            ->map(fn ($worker) => $worker->workstation?->line_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A crew's planning efficiency as a 0–1 factor.
     *
     * Crews live behind {@see WorkforceProvider}, so core cannot name a column
     * on them; a provider that records a `planning_efficiency_pct` exposes it on
     * the crew it returns. Absent or out of range reads as 100 %.
     */
    private function crewEfficiencyFactor(object $crew): float
    {
        $pct = (float) ($crew->planning_efficiency_pct ?? 100);

        return $pct > 0 && $pct <= 100 ? $pct / 100 : 1.0;
    }

    /**
     * Convert raw minute tallies into the display shape (rounded hours +
     * integer load percentage; null percentage when there is no capacity).
     *
     * `available_*` is the effective capacity load is measured against;
     * `calendar_*` is the undiminished shift-calendar figure beside it.
     */
    private function finalizeCells(array $cells): array
    {
        $out = [];
        foreach ($cells as $key => $cell) {
            $out[$key] = [
                'calendar_h' => round(($cell['calendar_minutes'] ?? $cell['available_minutes']) / 60, 1),
                'available_h' => round($cell['available_minutes'] / 60, 1),
                'planned_h' => round($cell['planned_minutes'] / 60, 1),
                'load_pct' => $cell['available_minutes'] > 0
                    ? (int) round($cell['planned_minutes'] / $cell['available_minutes'] * 100)
                    : null,
                'unestimated_count' => $cell['unestimated_count'],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    private function buildBuckets(Carbon $start, Carbon $end, string $granularity): array
    {
        $buckets = [];

        if ($granularity === 'day') {
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $buckets[] = [
                    'key' => $cursor->format('Y-m-d'),
                    'label' => $cursor->translatedFormat('D d.m'),
                    'start' => $cursor->copy()->startOfDay(),
                    'end' => $cursor->copy()->endOfDay(),
                ];
                $cursor->addDay();
            }

            return $buckets;
        }

        $cursor = $start->copy()->startOfWeek();
        while ($cursor->lte($end)) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();
            $buckets[] = [
                'key' => $weekStart->format('o-\WW'),
                'label' => __('Week').' '.$cursor->isoWeek(),
                'start' => $weekStart,
                'end' => $weekEnd,
            ];
            $cursor->addWeek();
        }

        return $buckets;
    }

    /**
     * Pre-index active shift minutes by line and ISO weekday, so per-day
     * availability is an O(1) lookup instead of re-scanning every shift for
     * every (resource, bucket, day). Global shifts (line_id null) are kept
     * separately and added to every line.
     *
     * @return array{global: array<int, int>, line: array<int, array<int, int>>}
     */
    private function buildShiftIndex(Collection $shifts): array
    {
        $index = ['global' => [], 'line' => []];

        foreach ($shifts as $shift) {
            $minutes = $this->shiftMinutes($shift);
            for ($dow = 1; $dow <= 7; $dow++) {
                if (! $this->shiftRunsOn($shift, $dow)) {
                    continue;
                }
                if ($shift->line_id === null) {
                    $index['global'][$dow] = ($index['global'][$dow] ?? 0) + $minutes;
                } else {
                    $index['line'][$shift->line_id][$dow] = ($index['line'][$shift->line_id][$dow] ?? 0) + $minutes;
                }
            }
        }

        return $index;
    }

    /**
     * Shift minutes available to a given line on an ISO weekday: global shifts
     * plus that line's own shifts. A null line resolves to global shifts only.
     */
    private function shiftMinutesForLine(array $index, ?int $lineId, int $isoDow): int
    {
        $global = $index['global'][$isoDow] ?? 0;
        $line = $lineId !== null ? ($index['line'][$lineId][$isoDow] ?? 0) : 0;

        return $global + $line;
    }

    /**
     * Walk each day of a bucket that falls within the visible range, summing
     * the integer the callback returns for that day. Shared scaffolding for
     * the line and crew availability calculations.
     */
    private function eachDayInBucket(array $bucket, Carbon $rangeStart, Carbon $rangeEnd, callable $perDay): int
    {
        $from = $bucket['start']->greaterThan($rangeStart) ? $bucket['start'] : $rangeStart;
        $to = $bucket['end']->lessThan($rangeEnd) ? $bucket['end'] : $rangeEnd;

        $total = 0;
        $cursor = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();
        while ($cursor->lte($last)) {
            $total += $perDay($cursor, (int) $cursor->format('N'));
            $cursor->addDay();
        }

        return $total;
    }

    /**
     * Available shift minutes for a line within a bucket (machine capacity).
     */
    private function lineAvailableMinutes(array $shiftIndex, int $lineId, array $bucket, Carbon $rangeStart, Carbon $rangeEnd): int
    {
        return $this->eachDayInBucket(
            $bucket,
            $rangeStart,
            $rangeEnd,
            fn (Carbon $date, int $dow) => $this->shiftMinutesForLine($shiftIndex, $lineId, $dow)
        );
    }

    /**
     * Labor-minutes a crew supplies within a bucket: for each active,
     * non-absent worker, their line's shift minutes on each working day, less
     * the crew's break windows for that day (floored at zero per worker-day).
     * A shift's full minutes count on the weekday it starts, and that date's
     * absences apply — a night shift crossing midnight belongs to the evening
     * it began, as {@see WorkforceProvider::absentWorkerIds()} defines.
     *
     * @param  array<string, array<int>>  $absentByDay  per-day absent-worker cache (by reference)
     */
    private function crewAvailableMinutes(object $crew, array $shiftIndex, array $bucket, Carbon $rangeStart, Carbon $rangeEnd, array &$absentByDay): int
    {
        if ($crew->workers->isEmpty()) {
            return 0;
        }

        return $this->eachDayInBucket($bucket, $rangeStart, $rangeEnd, function (Carbon $date, int $dow) use ($crew, $shiftIndex, &$absentByDay) {
            $dateKey = $date->format('Y-m-d');
            if (! isset($absentByDay[$dateKey])) {
                $absentByDay[$dateKey] = $this->availability->absentWorkerIds($date);
            }
            $absent = $absentByDay[$dateKey];
            $breakMinutes = $this->crewBreakMinutes($crew, $date);

            $dayTotal = 0;
            foreach ($crew->workers as $worker) {
                if (in_array($worker->id, $absent, true)) {
                    continue;
                }
                $shiftMinutes = $this->shiftMinutesForLine($shiftIndex, $worker->workstation?->line_id, $dow);
                if ($shiftMinutes > 0) {
                    $dayTotal += max(0, $shiftMinutes - $breakMinutes);
                }
            }

            return $dayTotal;
        });
    }

    /**
     * Whether a shift is active on the given ISO weekday (1=Mon .. 7=Sun).
     * `days_of_week` is normalized to ISO integers by {@see \App\Casts\DaysOfWeek}.
     * Matches {@see Shift::current()}: an unset (null) list runs every day, an
     * explicitly empty list runs on no day.
     */
    private function shiftRunsOn(Shift $shift, int $isoDow): bool
    {
        $days = $shift->days_of_week;
        if (! is_array($days)) {
            return true; // null / unset = every day
        }

        return in_array($isoDow, $days, true); // empty array = no day
    }

    /**
     * Duration of a single shift occurrence in minutes. Computed from the
     * nominal clock times (not wall-clock instants), so an "8h shift" always
     * counts 480 minutes regardless of timezone or a DST transition that day.
     * A shift whose end is at or before its start is treated as crossing
     * midnight; a zero-length shift (start == end) counts as zero, not a day.
     */
    private function shiftMinutes(Shift $shift): int
    {
        return $this->clockSpanMinutes($shift->start_time, $shift->end_time);
    }

    /**
     * Total crew break-window minutes that apply on the given date. Defers the
     * weekday-membership check to {@see \App\Models\CrewBreakWindow::appliesOn()}
     * so the empty-days semantics ("never applies") stay consistent with the
     * rest of the app rather than being re-implemented here.
     */
    private function crewBreakMinutes(object $crew, Carbon $date): int
    {
        $total = 0;
        foreach ($crew->breakWindows as $window) {
            if (! $window->appliesOn($date)) {
                continue;
            }
            $total += $this->clockSpanMinutes($window->start_time, $window->end_time);
        }

        return $total;
    }

    /**
     * Minutes between two nominal clock times ("HH:MM" / "HH:MM:SS"), wrapping
     * past midnight when the end is at or before the start. Pure clock math —
     * independent of timezone and DST, which is what nominal shift/break spans
     * should be.
     */
    private function clockSpanMinutes(string $start, string $end): int
    {
        $minutes = $this->clockMinutes($end) - $this->clockMinutes($start);
        if ($minutes < 0) {
            $minutes += 24 * 60;
        }

        return $minutes;
    }

    private function clockMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }

    /**
     * Minutes consumed by scheduled maintenance within the bucket. The event's
     * span is clamped to the bucket so an event crossing a bucket boundary only
     * subtracts the minutes that actually fall inside it.
     */
    private function maintenanceMinutes(Collection $events, array $bucket): int
    {
        $total = 0;
        foreach ($events as $event) {
            if (! $event->scheduled_at) {
                continue;
            }
            $end = $event->scheduled_end_at ?? $event->scheduled_at->copy()->addMinutes(60);
            $total += $this->overlapMinutes($event->scheduled_at, $end, $bucket);
        }

        return $total;
    }

    /**
     * Minutes of [$start, $end] that fall within a bucket.
     */
    private function overlapMinutes(Carbon $start, Carbon $end, array $bucket): int
    {
        $overlapStart = $start->greaterThan($bucket['start']) ? $start : $bucket['start'];
        $overlapEnd = $end->lessThan($bucket['end']) ? $end : $bucket['end'];
        $minutes = (int) $overlapStart->diffInMinutes($overlapEnd, false);

        return max(0, $minutes);
    }

    /**
     * Resolve the bucket a non-minute-planned order belongs to, by its due
     * date or — failing that — its ISO week number. Returns null when the
     * placement date is not within any visible bucket.
     */
    private function bucketKeyForOrder(WorkOrder $wo, array $buckets): ?string
    {
        $date = $wo->planned_start_at ?? $wo->due_date;

        if (! $date && $wo->week_number && ! empty($buckets)) {
            $year = $wo->production_year ?? (int) $buckets[0]['start']->isoWeekYear;
            $date = $buckets[0]['start']->copy()->setISODate($year, (int) $wo->week_number)->startOfWeek();
        }

        if (! $date) {
            return null;
        }

        foreach ($buckets as $bucket) {
            if ($date->between($bucket['start'], $bucket['end'])) {
                return $bucket['key'];
            }
        }

        return null;
    }
}
