<?php

namespace App\Services\Dashboard;

use App\Models\Batch;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\ProductionDowntime;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Production\ShiftMonitorService;
use App\Support\ModuleRegistry;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model behind the "Plant overview" header of the admin dashboard: the
 * plant shift running now and what every active line is doing.
 *
 * Line state is one of four words shared by every dashboard section:
 *
 *  - `running` — a station on the line reports RUNNING (or, on a line with no
 *    machine collector, a work order is IN_PROGRESS);
 *  - `down`    — FAULT, MAINTENANCE, STOPPED with an open unplanned stop, an
 *    in-progress maintenance event on the line, or an open unplanned stop
 *    reported by hand;
 *  - `blocked` — WAITING (material, operator…) or a BLOCKED work order;
 *  - `idle`    — IDLE, SETUP, CLEANING, a planned stop, or nothing going on.
 *
 * When signals disagree the worst one wins (down > blocked > running > idle),
 * ties going to the one that started first. `LineStatus` is a kanban column for
 * work orders, not a machine state, and is deliberately not consulted.
 *
 * `since` for work-order signals is the order's `updated_at` — there is no
 * `blocked_at` column, so it is a heuristic that moves on unrelated edits.
 *
 * Tenant boundary: Workstation, WorkstationState and MaintenanceEvent carry no
 * tenant scope, so every query below is constrained by the IDs of the
 * tenant-scoped Line query. Query count is fixed regardless of line count.
 */
class PlantOverviewService
{
    private const STATE_PRIORITY = ['down' => 3, 'blocked' => 2, 'running' => 1, 'idle' => 0];

    public function __construct(private ShiftMonitorService $monitor) {}

    /**
     * @return array{shift: array<string, mixed>, lines: array<int, array<string, mixed>>, timeline: array<string, mixed>}
     */
    public function overview(): array
    {
        return [
            'shift' => $this->currentShift(),
            'lines' => $this->lineStates(),
            'timeline' => $this->lineTimeline(),
        ];
    }

    /**
     * What every active line did, minute by minute, from the start of the plant
     * shift until now — the "Line activity" bars — and its uptime.
     *
     * Aggregation: in each minute every signal about the line casts a candidate
     * state, and the worst one wins, exactly as for the current state (down >
     * blocked > running > idle, ties to the earliest start). The signals are:
     *
     *  - each reporting station's state slices, through the same mapping as
     *    lineStates() — so one faulted station makes the whole line Down for
     *    that interval, and one running station among idle ones makes it
     *    Running. Workstations carry no "critical" flag, so every active
     *    station counts as critical;
     *  - stops not explained by a reporting station: line-level ones and ones
     *    logged by hand against a station with no collector;
     *  - maintenance events in progress or completed (maintenance module on);
     *  - a work order BLOCKED now, from its `updated_at` (no `blocked_at`);
     *  - on a line with no collector only: its work orders' batches from start
     *    to completion, and an IN_PROGRESS order with no batch from its
     *    `updated_at` — the order history standing in for machine states.
     *
     * Minutes nobody said anything about are `none` (drawn hatched, counted
     * towards nothing) on a collector line, and `idle` on a line without one.
     * A line nothing at all is known about has `has_data = false` and a null
     * uptime — never a fake 0 %.
     *
     * Uptime = running minutes ÷ minutes elapsed since the shift started.
     *
     * @return array{start: string, end: string, now: string, span: int, elapsed: int, is_live: bool, lines: array<int, array<string, mixed>>}
     */
    public function lineTimeline(?ShiftWindow $window = null): array
    {
        $window ??= ShiftWindow::current();
        $now = Carbon::now();
        $asOf = $now->greaterThan($window->end) ? $window->end->copy() : $now;
        $startTs = $window->start->getTimestamp();
        $elapsed = max(0, (int) floor(($asOf->getTimestamp() - $startTs) / 60));

        $payload = [
            'start' => $window->start->toIso8601String(),
            'end' => $window->end->toIso8601String(),
            'now' => $now->toIso8601String(),
            'span' => $window->durationMinutes(),
            'elapsed' => $elapsed,
            'is_live' => $window->contains($now),
            'lines' => [],
        ];

        $lines = Line::active()->orderBy('name')->get(['id', 'name']);

        if ($lines->isEmpty()) {
            return $payload;
        }

        $lineIds = $lines->pluck('id');

        $stations = Workstation::whereIn('line_id', $lineIds)
            ->where('is_active', true)
            ->get(['id', 'line_id', 'name']);
        $stationIds = $stations->pluck('id');
        $stationLine = $stations->pluck('line_id', 'id');
        $stationsByLine = $stations->groupBy('line_id');

        $machine = $this->monitor->stateTimelines($stations, $window);

        $overlapsWindow = fn ($q, string $from, string $to) => $q
            ->whereNotNull($from)
            ->where($from, '<', $window->end)
            ->where(fn ($q2) => $q2->whereNull($to)->orWhere($to, '>', $window->start));

        // Same rule as lineStates(): stops on a reporting station are already
        // attached to its slices.
        $manualStops = $overlapsWindow(ProductionDowntime::with('reason'), 'started_at', 'ended_at')
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stationIds))
            ->get()
            ->reject(fn (ProductionDowntime $d) => $d->workstation_id && isset($machine[$d->workstation_id]))
            ->groupBy(fn (ProductionDowntime $d) => ($d->workstation_id ? $stationLine->get($d->workstation_id) : null) ?? $d->line_id);

        // Line id rides along as a subselect: Batch has no line of its own, and
        // whereHas keeps the work orders' tenant scope in force.
        $batches = $overlapsWindow(Batch::query(), 'started_at', 'completed_at')
            ->whereIn('status', [Batch::STATUS_IN_PROGRESS, Batch::STATUS_DONE])
            ->whereHas('workOrder', fn ($q) => $q->whereIn('line_id', $lineIds))
            ->select(['id', 'work_order_id', 'status', 'started_at', 'completed_at'])
            ->addSelect(['line_id' => WorkOrder::select('line_id')->whereColumn('work_orders.id', 'batches.work_order_id')])
            ->get()
            ->groupBy('line_id');

        $orders = WorkOrder::whereIn('line_id', $lineIds)
            ->whereIn('status', [WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_IN_PROGRESS])
            ->get(['id', 'line_id', 'status', 'updated_at'])
            ->groupBy('line_id');

        $maintenance = $this->maintenanceInWindow($lineIds, $stationIds, $stationLine, $window);

        $payload['lines'] = $lines->map(function (Line $line) use (
            $stationsByLine, $machine, $manualStops, $batches, $orders, $maintenance, $window, $now, $startTs, $elapsed
        ) {
            $slots = array_fill(0, $elapsed, null);
            $minute = fn (?Carbon $at) => $at === null ? $elapsed : (int) round(($at->getTimestamp() - $startTs) / 60);
            $nowMinute = $minute($now);
            $hasCollector = false;

            foreach ($stationsByLine->get($line->id) ?? [] as $station) {
                foreach ($machine[$station->id] ?? [] as $slice) {
                    $hasCollector = true;
                    $stop = $slice['downtime'];
                    $this->paint($slots, $slice['from'], $slice['to'], $this->machineCandidate(
                        $slice['state'], $stop, $stop?->started_at ?? $slice['started_at'], $slice['started_at'],
                    ));
                }
            }

            foreach ($manualStops->get($line->id) ?? [] as $stop) {
                $this->paint($slots, $minute($stop->started_at), $stop->ended_at ? $minute($stop->ended_at) : $nowMinute, $this->stopCandidate($stop));
            }

            foreach ($maintenance->get($line->id) ?? [] as $event) {
                $end = $event->completed_at
                    ? $minute($event->completed_at)
                    : ($event->status === MaintenanceEvent::STATUS_IN_PROGRESS ? $nowMinute : 0);
                $this->paint($slots, $minute($event->started_at), $end, $this->candidate('down', $event->started_at, __('Maintenance'), 'manual'));
            }

            $lineBatches = $batches->get($line->id) ?? collect();

            foreach ($orders->get($line->id) ?? [] as $order) {
                $since = $order->updated_at?->copy()->max($window->start);

                if ($order->status === WorkOrder::STATUS_BLOCKED) {
                    $this->paint($slots, $minute($since), $nowMinute, $this->candidate('blocked', $since, __('Work order blocked'), 'manual'));
                } elseif (! $hasCollector && ! $lineBatches->contains('work_order_id', $order->id)) {
                    $this->paint($slots, $minute($since), $nowMinute, $this->candidate('running', $since, null, 'manual'));
                }
            }

            if (! $hasCollector) {
                foreach ($lineBatches as $batch) {
                    $end = $batch->completed_at
                        ? $minute($batch->completed_at)
                        : ($batch->status === Batch::STATUS_IN_PROGRESS ? $nowMinute : 0);
                    $this->paint($slots, $minute($batch->started_at), $end, $this->candidate('running', $batch->started_at, null, 'manual'));
                }
            }

            $hasData = $hasCollector || array_filter($slots) !== [];
            $filler = $hasCollector ? 'none' : 'idle';

            $segments = $hasData ? $this->segments($slots, $filler, $window) : [];
            $minutes = ['running' => 0, 'idle' => 0, 'blocked' => 0, 'down' => 0, 'none' => 0];
            foreach ($segments as $segment) {
                $minutes[$segment['state']] += $segment['minutes'];
            }

            return [
                'id' => $line->id,
                'name' => $line->name,
                'has_collector' => $hasCollector,
                'has_data' => $hasData,
                'uptime' => $hasData && $elapsed > 0 ? (int) round($minutes['running'] / $elapsed * 100) : null,
                'minutes' => $minutes,
                'segments' => $segments,
            ];
        })->values()->all();

        return $payload;
    }

    /**
     * Write a candidate into every minute of [from, to) where it beats what is
     * already there — resolve()'s rule, one minute at a time.
     *
     * @param  array<int, ?array<string, mixed>>  $slots
     */
    private function paint(array &$slots, int $from, int $to, array $candidate): void
    {
        $to = min($to, count($slots));
        $priority = self::STATE_PRIORITY[$candidate['state']];
        $since = $candidate['since']?->getTimestamp() ?? PHP_INT_MAX;

        for ($m = max(0, $from); $m < $to; $m++) {
            $held = $slots[$m];
            if ($held === null
                || $priority > self::STATE_PRIORITY[$held['state']]
                || ($priority === self::STATE_PRIORITY[$held['state']] && $since < ($held['since']?->getTimestamp() ?? PHP_INT_MAX))) {
                $slots[$m] = $candidate;
            }
        }
    }

    /**
     * Run-length encode the painted minutes into the bar's segments.
     *
     * @param  array<int, ?array<string, mixed>>  $slots
     * @return array<int, array{state: string, from: int, minutes: int, starts_at: string, ends_at: string, reason: ?string, needs_reason: bool, source: ?string}>
     */
    private function segments(array $slots, string $filler, ShiftWindow $window): array
    {
        $segments = [];
        $open = null;

        foreach ($slots as $m => $slot) {
            $state = $slot['state'] ?? $filler;
            $reason = $slot['reason'] ?? null;
            $needsReason = $slot['needs_reason'] ?? false;

            if ($open && $open['state'] === $state && $open['reason'] === $reason && $open['needs_reason'] === $needsReason) {
                $open['minutes']++;

                continue;
            }

            if ($open) {
                $segments[] = $open;
            }
            $open = [
                'state' => $state,
                'from' => $m,
                'minutes' => 1,
                'reason' => $reason,
                'needs_reason' => $needsReason,
                'source' => $slot['source'] ?? null,
            ];
        }

        if ($open) {
            $segments[] = $open;
        }

        return array_map(fn (array $s) => [
            'state' => $s['state'],
            'from' => $s['from'],
            'minutes' => $s['minutes'],
            'starts_at' => $window->start->copy()->addMinutes($s['from'])->toIso8601String(),
            'ends_at' => $window->start->copy()->addMinutes($s['from'] + $s['minutes'])->toIso8601String(),
            'reason' => $s['reason'],
            'needs_reason' => $s['needs_reason'],
            'source' => $s['source'],
        ], $segments);
    }

    /**
     * The plant shift running now. With no shift configured (or none scheduled
     * now) this is ShiftWindow's fallback 12h window, flagged `is_default`.
     *
     * @return array{name: ?string, code: ?string, start: string, end: string, minutes_left: ?int, is_live: bool, is_default: bool}
     */
    public function currentShift(): array
    {
        $window = ShiftWindow::current();
        $now = Carbon::now();
        $isLive = $window->contains($now);

        return [
            'name' => $window->shift?->name,
            'code' => $window->shift?->code,
            'start' => $window->start->toIso8601String(),
            'end' => $window->end->toIso8601String(),
            'minutes_left' => $isLive ? (int) ceil(abs($now->diffInSeconds($window->end)) / 60) : null,
            'is_live' => $isLive,
            'is_default' => $window->shift === null,
        ];
    }

    /**
     * Current state of every active line.
     *
     * @return array<int, array{id: int, name: string, state: string, since: ?string, reason: ?string, needs_reason: bool, source: string, has_collector: bool}>
     */
    public function lineStates(): array
    {
        $lines = Line::active()->orderBy('name')->get(['id', 'name']);

        if ($lines->isEmpty()) {
            return [];
        }

        $lineIds = $lines->pluck('id');

        $stations = Workstation::whereIn('line_id', $lineIds)
            ->where('is_active', true)
            ->get(['id', 'line_id', 'name']);
        $stationLine = $stations->pluck('line_id', 'id');
        $stationsByLine = $stations->groupBy('line_id');

        $machine = $this->monitor->currentStates($stations);

        // Stops not already explained by a reporting station: line-level ones,
        // and ones logged by hand against a station with no collector.
        $manualStops = ProductionDowntime::with('reason')
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stations->pluck('id')))
            ->whereNull('ended_at')
            ->get()
            ->reject(fn (ProductionDowntime $d) => $d->workstation_id && isset($machine[$d->workstation_id]))
            ->groupBy(fn (ProductionDowntime $d) => ($d->workstation_id ? $stationLine->get($d->workstation_id) : null) ?? $d->line_id);

        $orders = WorkOrder::whereIn('line_id', $lineIds)
            ->whereIn('status', [WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_IN_PROGRESS])
            ->get(['id', 'line_id', 'status', 'updated_at'])
            ->groupBy('line_id');

        $maintenance = $this->maintenanceByLine($lineIds, $stations->pluck('id'), $stationLine);

        return $lines->map(function (Line $line) use ($stationsByLine, $machine, $manualStops, $orders, $maintenance) {
            $candidates = [];
            $hasCollector = false;

            foreach ($stationsByLine->get($line->id) ?? [] as $station) {
                if (! isset($machine[$station->id])) {
                    continue;
                }
                $hasCollector = true;
                $candidates[] = $this->stationCandidate($machine[$station->id]);
            }

            foreach ($manualStops->get($line->id) ?? [] as $stop) {
                $candidates[] = $this->stopCandidate($stop);
            }

            foreach ($maintenance->get($line->id) ?? [] as $event) {
                $candidates[] = $this->candidate('down', $event->started_at ?? $event->scheduled_at, __('Maintenance'), 'manual');
            }

            foreach ($orders->get($line->id) ?? [] as $order) {
                if ($order->status === WorkOrder::STATUS_BLOCKED) {
                    $candidates[] = $this->candidate('blocked', $order->updated_at, __('Work order blocked'), 'manual');
                } elseif (! $hasCollector) {
                    // Only a line nobody's machines report on is "running" on
                    // the strength of an order; otherwise the machine decides.
                    $candidates[] = $this->candidate('running', $order->updated_at, null, 'manual');
                }
            }

            $winner = self::resolve($candidates) ?? $this->candidate('idle', null, null, 'manual');

            return [
                'id' => $line->id,
                'name' => $line->name,
                'state' => $winner['state'],
                'since' => $winner['since']?->toIso8601String(),
                'reason' => $winner['reason'],
                'needs_reason' => $winner['needs_reason'],
                'source' => $winner['source'],
                'has_collector' => $hasCollector,
            ];
        })->values()->all();
    }

    /**
     * The worst candidate; among equals the one that started first (a missing
     * `since` sorts last).
     *
     * @param  array<int, array{state: string, since: ?Carbon}>  $candidates
     */
    public static function resolve(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b) {
            $byState = self::STATE_PRIORITY[$b['state']] <=> self::STATE_PRIORITY[$a['state']];
            if ($byState !== 0) {
                return $byState;
            }

            return ($a['since']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['since']?->getTimestamp() ?? PHP_INT_MAX);
        });

        return $candidates[0];
    }

    /**
     * Map a station's machine state to a line state:
     * RUNNING → running; FAULT / MAINTENANCE → down; STOPPED → down with an
     * open unplanned (or unclassified) stop, otherwise idle; WAITING → blocked;
     * IDLE / SETUP / CLEANING → idle.
     *
     * @param  array{state: string, since: Carbon, stateSince: Carbon, stop: ?ProductionDowntime}  $current
     */
    private function stationCandidate(array $current): array
    {
        return $this->machineCandidate($current['state'], $current['stop'], $current['since'], $current['stateSince']);
    }

    /**
     * The one machine-state → line-state mapping (see stationCandidate()),
     * shared by the current state and the timeline so the two can't drift.
     *
     * @param  ?Carbon  $since  when the stop (or the state) began
     * @param  ?Carbon  $stateSince  when the state row began
     */
    private function machineCandidate(string $state, ?ProductionDowntime $stop, ?Carbon $since, ?Carbon $stateSince): array
    {
        $stopReason = $this->reasonName($stop);
        $needsReason = $stop !== null && ($stop->needs_reason || $stop->reason === null);

        return match ($state) {
            WorkstationState::RUNNING => $this->candidate('running', $stateSince, null, 'machine'),
            WorkstationState::FAULT => $this->candidate('down', $since, $stopReason ?? __('Fault'), 'machine', $needsReason),
            WorkstationState::MAINTENANCE => $this->candidate('down', $since, $stopReason ?? __('Maintenance'), 'machine'),
            WorkstationState::WAITING => $this->candidate('blocked', $since, $stopReason ?? __('Waiting for material'), 'machine'),
            WorkstationState::STOPPED => $stop && $this->isUnplanned($stop)
                ? $this->candidate('down', $since, $stopReason, 'machine', $needsReason)
                : $this->candidate('idle', $since, $stopReason, 'machine'),
            default => $this->candidate('idle', $stateSince, null, 'machine'),
        };
    }

    /** A stop reported by hand or against the whole line. */
    private function stopCandidate(ProductionDowntime $stop): array
    {
        $needsReason = $stop->needs_reason || $stop->reason === null;

        return $this->isUnplanned($stop)
            ? $this->candidate('down', $stop->started_at, $this->reasonName($stop), 'manual', $needsReason)
            : $this->candidate('idle', $stop->started_at, $this->reasonName($stop), 'manual');
    }

    /**
     * The cause a person gave the stop. A machine-opened stop carries an AUTO-*
     * placeholder reason flagged `needs_reason` — that name is not a cause, so
     * it is withheld and the caller's state-specific fallback is used instead.
     */
    private function reasonName(?ProductionDowntime $stop): ?string
    {
        return $stop && ! $stop->needs_reason ? $stop->reason?->name : null;
    }

    /** Unclassified stops count as unplanned until somebody says otherwise. */
    private function isUnplanned(ProductionDowntime $stop): bool
    {
        return $stop->needs_reason
            || $stop->reason === null
            || ($stop->reason->kind?->countsAsAvailabilityLoss() ?? true);
    }

    private function candidate(string $state, ?Carbon $since, ?string $reason, string $source, bool $needsReason = false): array
    {
        return [
            'state' => $state,
            'since' => $since,
            'reason' => $reason,
            'needs_reason' => $needsReason,
            'source' => $source,
        ];
    }

    /**
     * In-progress maintenance events grouped by line, an event booked against
     * a station counting for that station's line. Empty when the maintenance
     * module is switched off.
     *
     * @param  Collection<int, int>  $lineIds
     * @param  Collection<int, int>  $stationIds
     * @param  Collection<int, int>  $stationLine  station id → line id
     * @return Collection<int, Collection<int, MaintenanceEvent>>
     */
    private function maintenanceByLine(Collection $lineIds, Collection $stationIds, Collection $stationLine): Collection
    {
        if (! ModuleRegistry::isModuleEnabled('maintenance')) {
            return collect();
        }

        return MaintenanceEvent::where('status', MaintenanceEvent::STATUS_IN_PROGRESS)
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stationIds))
            ->get(['id', 'line_id', 'workstation_id', 'started_at', 'scheduled_at'])
            ->groupBy(fn (MaintenanceEvent $e) => $e->line_id && $lineIds->contains($e->line_id)
                ? $e->line_id
                : $stationLine->get($e->workstation_id));
    }

    /**
     * Maintenance events overlapping the shift — in progress, or completed
     * after it began — grouped by line like maintenanceByLine(). Empty when
     * the maintenance module is switched off.
     *
     * @param  Collection<int, int>  $lineIds
     * @param  Collection<int, int>  $stationIds
     * @param  Collection<int, int>  $stationLine  station id → line id
     * @return Collection<int, Collection<int, MaintenanceEvent>>
     */
    private function maintenanceInWindow(Collection $lineIds, Collection $stationIds, Collection $stationLine, ShiftWindow $window): Collection
    {
        if (! ModuleRegistry::isModuleEnabled('maintenance')) {
            return collect();
        }

        return MaintenanceEvent::whereIn('status', [MaintenanceEvent::STATUS_IN_PROGRESS, MaintenanceEvent::STATUS_COMPLETED])
            ->whereNotNull('started_at')
            ->where('started_at', '<', $window->end)
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>', $window->start))
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stationIds))
            ->get(['id', 'line_id', 'workstation_id', 'status', 'started_at', 'completed_at'])
            ->groupBy(fn (MaintenanceEvent $e) => $e->line_id && $lineIds->contains($e->line_id)
                ? $e->line_id
                : $stationLine->get($e->workstation_id));
    }
}
