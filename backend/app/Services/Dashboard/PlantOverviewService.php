<?php

namespace App\Services\Dashboard;

use App\Models\Batch;
use App\Models\Inspection;
use App\Models\Issue;
use App\Models\IssueAction;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProductionDowntime;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Production\ShiftMonitorService;
use App\Support\ModuleRegistry;
use App\Support\ShiftWindow;
use App\Support\TabRegistry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model behind the "Plant overview" header of the admin dashboard: the
 * plant shift running now and what every active line is doing.
 *
 * Line state is one of five words shared by every dashboard section:
 *
 *  - `running` — a station on the line reports RUNNING (or, on a line with no
 *    machine collector, a work order is IN_PROGRESS);
 *  - `down`    — FAULT, MAINTENANCE, STOPPED with an open unplanned stop, an
 *    in-progress maintenance event on the line, or an open unplanned stop
 *    reported by hand;
 *  - `blocked` — WAITING (material, operator…) or a BLOCKED work order;
 *  - `idle`    — IDLE, SETUP, CLEANING, a planned stop, or nothing going on;
 *  - `unknown` — the line has a machine collector but none of its stations has
 *    been heard from for NO_SIGNAL_AFTER_MINUTES ("No data"). Sections treat it
 *    as missing data — an empty state — never as idle or as zero output.
 *
 * When signals disagree the worst one wins (down > blocked > running > idle >
 * unknown), ties going to the one that started first. A silent station's
 * machine state casts no vote, so one fresh station decides for the line, and
 * anything a person reported still shows: a stop logged or classified by hand
 * (on the line or on any station, silent or not), a maintenance event, a
 * blocked order. Only the machine's own unclassified AUTO-* stop on a silent
 * station is dropped with its state — it is the last state, not news. `LineStatus` is a kanban column for
 * work orders, not a machine state, and is deliberately not consulted.
 *
 * `since` for work-order signals is the order's `updated_at` — there is no
 * `blocked_at` column, so it is a heuristic that moves on unrelated edits.
 *
 * Tenant boundary: Workstation, WorkstationState and MaintenanceEvent carry no
 * tenant scope, so every query below is constrained by the IDs of the
 * tenant-scoped Line query. Query count is fixed regardless of line count.
 *
 * needsAction() is the dashboard's "Needs action" list. It is NOT the alert
 * badge in the menu (AlertController::totalCount: open issues + overdue work
 * orders + blocked work orders). The two overlap only on blocked work: the
 * badge counts every BLOCKED order, the list one row per blocked LINE. The list
 * adds what the badge does not count — down lines, low stock, failed inbound
 * inspections, quarantined lots, overdue corrective actions — and leaves out
 * plain open issues and overdue orders, which the Alerts page covers.
 *
 * Shift OEE ({@see shiftOee()}) is the live figure from the shift monitor's
 * machine data, from shift start until now — the numbers Plant Board shows on
 * its tiles. `oee_records` is deliberately not read: those rows are the nightly
 * settlement of a finished day, not the state of the shift in progress.
 * Stations are combined by summing what the percentages are made of, never by
 * averaging percentages:
 *
 *  - availability = Σ running minutes / Σ opportunity minutes (planned stops
 *    excluded), so each station weighs by its planned production time;
 *  - performance  = Σ min(produced, expected) / Σ expected over stations with
 *    an ideal rate, so each weighs by its ideal pieces and one station beating
 *    nameplate cannot hide a slow one (the per-station cap the board applies);
 *  - quality      = Σ good / Σ produced, weighted by pieces made.
 *
 * On a line whose stations work in series a piece is counted at every station
 * it passes; the ratios stay meaningful, which is why no piece count is shown.
 */
class PlantOverviewService
{
    private const STATE_PRIORITY = ['down' => 4, 'blocked' => 3, 'running' => 2, 'idle' => 1, 'unknown' => 0];

    /** Machine states whose line state stationCandidate() takes from the open stops. */
    private const STATES_READING_STOPS = [
        WorkstationState::FAULT, WorkstationState::STOPPED, WorkstationState::WAITING, WorkstationState::MAINTENANCE,
    ];

    /**
     * A machine-reported state not refreshed for longer than this is no longer
     * believed. No per-station collector setting exists to borrow (the daemon
     * heartbeat and the operator-device window are seconds, per connection or
     * device), so it is a constant: well above the 60 s signal touch
     * (WorkstationStateMachine::SIGNAL_TOUCH_SECONDS) plus a poll period, and
     * above the dashboard's own 60 s refresh, so a healthy line never flickers.
     */
    public const NO_SIGNAL_AFTER_MINUTES = 10;

    public function __construct(
        private ShiftMonitorService $monitor,
        private ProductionTargets $targets,
        private ShiftPlanService $plan,
        private ShiftKpiService $kpis,
    ) {}

    /**
     * Everything the plant overview draws, refreshed together every 60 s.
     * Lines are resolved once and shared by the line states, the KPI tiles and
     * "Needs action", so a line never reads differently in two sections.
     * `kpis` links and `needs_action` buttons depend on who is asking; without
     * a user `needs_action` is null.
     *
     * @return array{shift: array<string, mixed>, lines: array<int, array<string, mixed>>, timeline: array<string, mixed>, oee: array<string, mixed>, downtime: array<string, mixed>, production: array<string, mixed>, kpis: array<string, mixed>, needs_action: ?array{total: int, items: array<int, array<string, mixed>>}}
     */
    public function overview(?User $user = null): array
    {
        $resolved = $this->resolveLines();
        $lines = $this->publicLines($resolved);

        return [
            'shift' => $this->currentShift(),
            'lines' => $lines,
            'timeline' => $this->lineTimeline(),
            'oee' => $this->shiftOee(),
            'downtime' => $this->downtime(),
            // Planned vs actual output of the plant shift, hour by hour.
            'production' => $this->plan->forShift(ShiftWindow::current()),
            'kpis' => $this->kpis->tiles($lines, $user),
            'needs_action' => $user ? $this->needsAction($user, $resolved) : null,
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
                        $slice['state'], collect([$stop])->filter(), $stop?->started_at ?? $slice['started_at'], $slice['started_at'],
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
     * @return array<int, array{id: int, name: string, state: 'running'|'down'|'blocked'|'idle'|'unknown', since: ?string, reason: ?string, needs_reason: bool, source: string, has_collector: bool}>
     */
    public function lineStates(): array
    {
        return $this->publicLines($this->resolveLines());
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
     * A machine-reported state nothing has refreshed since `$silentBefore`.
     * A state an operator set by hand has no collector behind it and never
     * goes silent.
     *
     * @param  array{source: string, seenAt: Carbon}  $current
     */
    private static function isSilent(array $current, Carbon $silentBefore): bool
    {
        return $current['source'] === 'machine' && $current['seenAt']->lt($silentBefore);
    }

    /**
     * Map a station's machine state to a line state:
     * RUNNING → running; FAULT / MAINTENANCE → down; STOPPED → down with an
     * open unplanned (or unclassified) stop, otherwise idle; WAITING → blocked;
     * IDLE / SETUP / CLEANING → idle.
     *
     * With several open stops, `since` is the oldest one's start, the reason the
     * oldest one somebody gave, and the line needs a reason while any of them
     * is still a placeholder — whatever the state, so MAINTENANCE and WAITING
     * ask for a cause exactly like FAULT and STOPPED do.
     *
     * @param  array{state: string, since: Carbon, stateSince: Carbon, stops: Collection<int, ProductionDowntime>}  $current
     */
    private function stationCandidate(array $current, int $workstationId): array
    {
        return [
            ...$this->machineCandidate($current['state'], $current['stops'], $current['since'], $current['stateSince']),
            // Where it happened, for "Needs action": the oldest open stop and the station.
            'stop' => $current['stops']->first(),
            'workstation_id' => $workstationId,
        ];
    }

    /**
     * The one machine-state → line-state mapping (see stationCandidate()),
     * shared by the current state and the timeline so the two can't drift.
     *
     * @param  Collection<int, ProductionDowntime>  $stops  the open stops, oldest first
     * @param  ?Carbon  $since  when the oldest stop (or the state) began
     * @param  ?Carbon  $stateSince  when the state row began
     */
    private function machineCandidate(string $state, Collection $stops, ?Carbon $since, ?Carbon $stateSince): array
    {
        $stopReason = $stops->map(fn (ProductionDowntime $d) => $this->reasonName($d))->filter()->first();
        $needsReason = $stops->contains(fn (ProductionDowntime $d) => $this->isPlaceholder($d));

        return match ($state) {
            WorkstationState::RUNNING => $this->candidate('running', $stateSince, null, 'machine'),
            WorkstationState::FAULT => $this->candidate('down', $since, $stopReason ?? __('Fault'), 'machine', $needsReason),
            WorkstationState::MAINTENANCE => $this->candidate('down', $since, $stopReason ?? __('Maintenance'), 'machine', $needsReason),
            WorkstationState::WAITING => $this->candidate('blocked', $since, $stopReason ?? __('Waiting for material'), 'machine', $needsReason),
            WorkstationState::STOPPED => $stops->contains(fn (ProductionDowntime $d) => $this->isUnplanned($d))
                ? $this->candidate('down', $since, $stopReason, 'machine', $needsReason)
                : $this->candidate('idle', $since, $stopReason, 'machine'),
            default => $this->candidate('idle', $stateSince, null, 'machine'),
        };
    }

    /** A stop reported by hand or against the whole line. */
    private function stopCandidate(ProductionDowntime $stop): array
    {
        $at = ['stop' => $stop, 'workstationId' => $stop->workstation_id];

        return $this->isUnplanned($stop)
            ? $this->candidate('down', $stop->started_at, $this->reasonName($stop), 'manual', $this->isPlaceholder($stop), ...$at)
            : $this->candidate('idle', $stop->started_at, $this->reasonName($stop), 'manual', ...$at);
    }

    /**
     * A stop the state machine opened and nobody has touched since: no
     * reporter, still on its AUTO-* reason.
     */
    private function isMachinePlaceholder(ProductionDowntime $stop): bool
    {
        return $stop->reported_by === null && str_starts_with((string) $stop->reason?->code, 'AUTO-');
    }

    /** A stop nobody has given a cause yet: flagged, or with no reason at all. */
    private function isPlaceholder(ProductionDowntime $stop): bool
    {
        return $stop->needs_reason || $stop->reason === null;
    }

    /**
     * The cause a person gave the stop. A machine-opened stop carries an AUTO-*
     * reason — flagged `needs_reason` when unplanned, not when planned
     * (cleaning, maintenance) — and that name is not a cause either way, so it
     * is withheld and the caller's state-specific fallback is used instead.
     * Same placeholder rule as ClassifyDowntimeRequest.
     */
    private function reasonName(?ProductionDowntime $stop): ?string
    {
        if (! $stop || $stop->needs_reason || str_starts_with((string) $stop->reason?->code, 'AUTO-')) {
            return null;
        }

        return $stop->reason?->name;
    }

    /** Unclassified stops count as unplanned until somebody says otherwise. */
    private function isUnplanned(ProductionDowntime $stop): bool
    {
        return $stop->needs_reason
            || $stop->reason === null
            || ($stop->reason->kind?->countsAsAvailabilityLoss() ?? true);
    }

    /**
     * `stop` and `workstationId` are internal — what a "Needs action" button
     * acts on — and never reach the public line payload.
     */
    private function candidate(string $state, ?Carbon $since, ?string $reason, string $source, bool $needsReason = false, ?ProductionDowntime $stop = null, ?int $workstationId = null): array
    {
        return [
            'state' => $state,
            'since' => $since,
            'reason' => $reason,
            'needs_reason' => $needsReason,
            'source' => $source,
            'stop' => $stop,
            'workstation_id' => $workstationId,
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

    /** Ties for the biggest loss go to the first in this order. */
    private const OEE_COMPONENTS = ['availability', 'performance', 'quality'];

    /**
     * Live OEE of the plant and of every active line for the current shift,
     * from shift start until now, against the targets of ProductionTargets.
     *
     * Values are percentages rounded to one decimal, null when there is
     * nothing to compute them from — never a fake 0. Lines are sorted by OEE,
     * best first, lines without a figure last.
     *
     * @return array{target: float, oee: ?float, availability: ?float, performance: ?float, quality: ?float, biggest_loss: ?string, gap_pts: ?float, on_target: ?bool, has_data: bool, lines: array<int, array{id: int, name: string, oee: ?float, availability: ?float, performance: ?float, quality: ?float, target: float, has_data: bool}>}
     */
    public function shiftOee(): array
    {
        $window = ShiftWindow::current();
        $lines = Line::active()->orderBy('name')->get(['id', 'name', 'planning_efficiency_pct']);

        $inputs = [];
        $stationLine = collect();
        if ($lines->isNotEmpty()) {
            $stations = Workstation::whereIn('line_id', $lines->pluck('id'))
                ->where('is_active', true)
                ->get(['id', 'line_id', 'ideal_rate_per_hour']);
            $inputs = $this->monitor->oeeInputs($stations, $window);
            $stationLine = $stations->pluck('line_id', 'id');
        }

        $byLine = [];
        foreach ($inputs as $stationId => $input) {
            $byLine[$stationLine[$stationId]][] = $input;
        }

        $lineRows = $lines->map(function (Line $line) use ($byLine) {
            $oee = self::aggregateOee($byLine[$line->id] ?? []);

            return [
                'id' => $line->id,
                'name' => $line->name,
                ...$oee,
                'target' => round($this->targets->oeeTargetForLine($line), 1),
            ];
        })->sort(function (array $a, array $b) {
            if (($a['oee'] === null) !== ($b['oee'] === null)) {
                return $a['oee'] === null ? 1 : -1;
            }

            return ($b['oee'] <=> $a['oee']) ?: strcmp($a['name'], $b['name']);
        })->values()->all();

        $plant = self::aggregateOee(array_values($inputs));
        $target = round($this->targets->plantOeeTarget($window), 1);

        return [
            'target' => $target,
            ...$plant,
            'biggest_loss' => $plant['oee'] !== null ? self::biggestLoss($plant) : null,
            'gap_pts' => $plant['oee'] !== null && $plant['oee'] < $target ? round($target - $plant['oee'], 1) : null,
            'on_target' => $plant['oee'] !== null ? $plant['oee'] >= $target : null,
            'lines' => $lineRows,
        ];
    }

    /**
     * Combine station inputs from ShiftMonitorService::oeeInputs() into one
     * A/P/Q/OEE figure — see the class docblock for the weighting. Rounded
     * only at the end, so a single station reproduces its board tile exactly.
     *
     * @param  array<int, array{opportunity: int, running: int, expected: float, produced: int, good: int}>  $inputs
     * @return array{oee: ?float, availability: ?float, performance: ?float, quality: ?float, has_data: bool}
     */
    public static function aggregateOee(array $inputs): array
    {
        $opportunity = $running = $produced = $good = 0;
        $expected = $credited = 0.0;

        foreach ($inputs as $input) {
            $opportunity += $input['opportunity'];
            $running += $input['running'];
            $produced += $input['produced'];
            $good += $input['good'];
            if ($input['expected'] > 0) {
                $expected += $input['expected'];
                $credited += min($input['produced'], $input['expected']);
            }
        }

        // No minute the station was meant to produce in: nothing to judge.
        if ($opportunity <= 0) {
            return ['oee' => null, 'availability' => null, 'performance' => null, 'quality' => null, 'has_data' => false];
        }

        $availability = $running / $opportunity * 100;
        $performance = $expected > 0 ? $credited / $expected * 100 : null;
        $quality = $produced > 0 ? $good / $produced * 100 : null;
        $oee = $performance !== null && $quality !== null
            ? $availability * $performance * $quality / 10000
            : null;

        return [
            'oee' => $oee !== null ? round($oee, 1) : null,
            'availability' => round($availability, 1),
            'performance' => $performance !== null ? round($performance, 1) : null,
            'quality' => $quality !== null ? round($quality, 1) : null,
            'has_data' => true,
        ];
    }

    /**
     * The component costing the most OEE: the lowest one. Ties go to the first
     * in availability → performance → quality order; null when none is known.
     *
     * @param  array{availability: ?float, performance: ?float, quality: ?float}  $oee
     */
    public static function biggestLoss(array $oee): ?string
    {
        $worst = null;
        foreach (self::OEE_COMPONENTS as $component) {
            $value = $oee[$component] ?? null;
            if ($value !== null && ($worst === null || $value < $oee[$worst])) {
                $worst = $component;
            }
        }

        return $worst;
    }

    /**
     * Downtime of the plant shift running now, grouped by reason.
     *
     * Uses the same window and overlap rule as Plant Board / Shift Monitor
     * (ShiftWindow::current(), `started_at < end AND (ended_at IS NULL OR
     * ended_at > start)`): each stop is clipped to the part of the window that
     * has already happened, an open stop running to now. Line-level stops
     * reported by hand count too.
     *
     * `total_minutes` and the reason rows add up every stop, station by
     * station, exactly as Plant Board lists them.
     *
     * The percentage is measured in *line time* instead, so it cannot pass
     * 100 %: four stations of one line faulting together, or a machine stop
     * and a hand-reported line stop covering the same hour, are one hour of
     * that line lost, not four. Per line, overlapping stops are merged first:
     *
     *  - `planned_minutes` — the net planned time *so far*: elapsed shift time
     *    minus the time covered by planned-kind stops, summed over active
     *    lines. The same net the OEE availability uses (shift time −
     *    DowntimeService::getPlannedMinutes), on elapsed rather than whole-shift
     *    time because the numerator is also "so far";
     *  - `loss_minutes` — time covered by unplanned, changeover or unclassified
     *    stops, outside planned stops;
     *  - `percent_of_planned` — loss over planned time. Planned stops appear in
     *    the total and the reason list but are not a loss.
     *
     * @return array{has_lines: bool, total_minutes: int, loss_minutes: int, planned_minutes: ?int, percent_of_planned: ?int, line_count: int, reasons: array<int, array<string, mixed>>, unclassified: ?array<string, mixed>}
     */
    public function downtime(): array
    {
        $lines = Line::active()->get(['id', 'name', 'code']);

        if ($lines->isEmpty()) {
            return [
                'has_lines' => false,
                'total_minutes' => 0,
                'loss_minutes' => 0,
                'planned_minutes' => null,
                'percent_of_planned' => null,
                'line_count' => 0,
                'reasons' => [],
                'unclassified' => null,
            ];
        }

        $window = ShiftWindow::current();
        $now = Carbon::now();
        $cap = $now->lessThan($window->end) ? $now : $window->end;

        $lineIds = $lines->pluck('id');
        $lineLabel = $lines->mapWithKeys(fn (Line $l) => [$l->id => $l->code ?: $l->name]);
        // Inactive stations too, so a stop logged before one was retired still
        // finds its line.
        $stationLine = Workstation::whereIn('line_id', $lineIds)->pluck('line_id', 'id');

        $stops = ProductionDowntime::with(['reason' => fn ($q) => $q->withTrashed()])
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stationLine->keys()))
            ->where('started_at', '<', $window->end)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $window->start))
            ->orderBy('started_at')
            ->get();

        $buckets = [];
        // line id → [[start, end], …] in unix seconds, for the line-time maths.
        $plannedSpans = [];
        $lossSpans = [];
        $totalSeconds = 0;
        $linesHit = [];

        foreach ($stops as $stop) {
            $lineId = ($stop->workstation_id ? $stationLine->get($stop->workstation_id) : null) ?? $stop->line_id;
            if (! $lineLabel->has($lineId)) {
                continue;
            }

            $span = $this->clip($stop, $window->start, $cap);
            if ($span === null) {
                continue;
            }
            $seconds = $span[1] - $span[0];

            $unclassified = $stop->needs_reason || $stop->reason === null;
            $kind = $unclassified ? 'unclassified' : ($stop->reason->kind?->value ?? 'unplanned');
            $key = $unclassified ? 'unclassified' : 'reason:'.$stop->reason->id;

            $buckets[$key] ??= [
                'id' => $unclassified ? null : $stop->reason->id,
                // Reason names are stored in English and translated on display,
                // as ShiftMonitorService::reasonLabel() does.
                'name' => $unclassified ? __('Unclassified') : __($stop->reason->name),
                'code' => $unclassified ? null : $stop->reason->code,
                'kind' => $kind,
                'seconds' => 0,
                'lines' => [],
                'count' => 0,
                'open' => false,
                'workstation_id' => null,
            ];
            $bucket = &$buckets[$key];
            $bucket['seconds'] += $seconds;
            $bucket['lines'][$lineLabel->get($lineId)] = true;
            $bucket['count']++;
            $bucket['open'] = $bucket['open'] || $stop->ended_at === null;
            // Oldest unclassified stop on a station: where "classify" sends you.
            $bucket['workstation_id'] ??= $unclassified ? $stop->workstation_id : null;
            unset($bucket);

            $totalSeconds += $seconds;
            $linesHit[$lineId] = true;
            if ($kind === 'planned') {
                $plannedSpans[$lineId][] = $span;
            } else {
                $lossSpans[$lineId][] = $span;
            }
        }

        $rows = collect($buckets)->map(function (array $b) {
            $lines = array_keys($b['lines']);
            sort($lines, SORT_NATURAL);

            return [...$b, 'minutes' => (int) round($b['seconds'] / 60), 'lines' => $lines];
        });

        $unclassified = $rows->get('unclassified');
        $reasons = $rows->forget('unclassified')
            ->sort(fn (array $a, array $b) => [$b['seconds'], $a['name']] <=> [$a['seconds'], $b['name']])
            ->map(fn (array $r) => collect($r)->only(['id', 'name', 'code', 'kind', 'minutes', 'lines', 'count', 'open'])->all())
            ->values()
            ->all();

        $elapsedSeconds = max(0, $cap->getTimestamp() - $window->start->getTimestamp());
        $plannedTime = 0;
        $lossSeconds = 0;
        foreach ($lineIds as $lineId) {
            $planned = self::mergeSpans($plannedSpans[$lineId] ?? []);
            $loss = self::mergeSpans($lossSpans[$lineId] ?? []);
            $plannedTime += $elapsedSeconds - self::spanLength($planned);
            $lossSeconds += self::spanLength($loss) - self::overlapLength($loss, $planned);
        }
        $plannedMinutes = (int) round($plannedTime / 60);

        return [
            'has_lines' => true,
            'total_minutes' => (int) round($totalSeconds / 60),
            'loss_minutes' => (int) round($lossSeconds / 60),
            'planned_minutes' => $plannedMinutes > 0 ? $plannedMinutes : null,
            'percent_of_planned' => $plannedTime > 0 ? (int) round($lossSeconds / $plannedTime * 100) : null,
            'line_count' => count($linesHit),
            'reasons' => $reasons,
            'unclassified' => $unclassified
                ? collect($unclassified)->only(['minutes', 'lines', 'count', 'open', 'workstation_id'])->all()
                : null,
        ];
    }

    /**
     * The part of a stop inside [$from, $to] as [start, end] unix seconds, an
     * open stop running to $to; null when nothing of it falls inside.
     *
     * @return array{0: int, 1: int}|null
     */
    private function clip(ProductionDowntime $stop, Carbon $from, Carbon $to): ?array
    {
        $start = max($stop->started_at->getTimestamp(), $from->getTimestamp());
        $end = min(($stop->ended_at ?? $to)->getTimestamp(), $to->getTimestamp());

        return $end > $start ? [$start, $end] : null;
    }

    /**
     * Sorted, non-overlapping union of spans.
     *
     * @param  array<int, array{0: int, 1: int}>  $spans
     * @return array<int, array{0: int, 1: int}>
     */
    private static function mergeSpans(array $spans): array
    {
        usort($spans, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($spans as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /** @param  array<int, array{0: int, 1: int}>  $spans  merged */
    private static function spanLength(array $spans): int
    {
        return array_sum(array_map(fn (array $s) => $s[1] - $s[0], $spans));
    }

    /**
     * Seconds two merged span lists have in common.
     *
     * @param  array<int, array{0: int, 1: int}>  $a
     * @param  array<int, array{0: int, 1: int}>  $b
     */
    private static function overlapLength(array $a, array $b): int
    {
        $total = 0;
        $i = $j = 0;
        while ($i < count($a) && $j < count($b)) {
            $total += max(0, min($a[$i][1], $b[$j][1]) - max($a[$i][0], $b[$j][0]));
            $a[$i][1] < $b[$j][1] ? $i++ : $j++;
        }

        return $total;
    }

    /** Most "Needs action" rows sent to the browser; `total` still counts all. */
    public const NEEDS_ACTION_LIMIT = 50;

    private const SEVERITY_PRIORITY = ['critical' => 1, 'warning' => 0];

    /**
     * @param  array<int, array{line: Line, winner: array<string, mixed>, has_collector: bool, order_id: ?int}>  $resolved
     * @return array<int, array<string, mixed>>
     */
    private function publicLines(array $resolved): array
    {
        return array_map(fn (array $r) => [
            'id' => $r['line']->id,
            'name' => $r['line']->name,
            'state' => $r['winner']['state'],
            'since' => $r['winner']['since']?->toIso8601String(),
            'reason' => $r['winner']['reason'],
            'needs_reason' => $r['winner']['needs_reason'],
            'source' => $r['winner']['source'],
            'has_collector' => $r['has_collector'],
        ], $resolved);
    }

    /**
     * Every active line with its winning signal and what a "Needs action"
     * button needs to act on it: the stop behind the state (if any), the
     * station it is on, and the line's blocked-or-running work order.
     *
     * @return array<int, array{line: Line, winner: array<string, mixed>, has_collector: bool, order_id: ?int}>
     */
    private function resolveLines(): array
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

        // Every open stop on these lines, read once: the station ones feed the
        // machine states, the rest are reported by hand.
        $openStops = ProductionDowntime::with('reason')
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stations->pluck('id')))
            ->whereNull('ended_at')
            ->orderBy('started_at')
            ->get();

        $machine = $this->monitor->currentStates($stations, $openStops);
        $silentBefore = Carbon::now()->subMinutes(self::NO_SIGNAL_AFTER_MINUTES);

        // Stops not already explained by a station's state: line-level ones,
        // ones on a station with no collector, and ones a person logged or
        // classified on a collector station — whether it went silent (a broken
        // or powered-off machine is exactly when that happens) or it reports a
        // state stationCandidate() doesn't read stops for (a quality hold on a
        // RUNNING machine). Dropped: stops a fresh downtime state already
        // carries, and the machine's own untouched AUTO-* placeholder.
        $manualStops = $openStops
            ->reject(function (ProductionDowntime $d) use ($machine, $silentBefore) {
                $current = $d->workstation_id ? ($machine[$d->workstation_id] ?? null) : null;
                if ($current === null) {
                    return false;
                }
                $explained = ! self::isSilent($current, $silentBefore)
                    && in_array($current['state'], self::STATES_READING_STOPS, true);

                return $explained || $this->isMachinePlaceholder($d);
            })
            ->groupBy(fn (ProductionDowntime $d) => ($d->workstation_id ? $stationLine->get($d->workstation_id) : null) ?? $d->line_id);

        $orders = WorkOrder::whereIn('line_id', $lineIds)
            ->whereIn('status', [WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_IN_PROGRESS])
            ->get(['id', 'line_id', 'status', 'updated_at'])
            ->groupBy('line_id');

        $maintenance = $this->maintenanceByLine($lineIds, $stations->pluck('id'), $stationLine);

        return $lines->map(function (Line $line) use ($stationsByLine, $machine, $manualStops, $orders, $maintenance, $silentBefore) {
            $candidates = [];
            $hasCollector = false;
            $anyFresh = false;
            $lastSeen = null;

            foreach ($stationsByLine->get($line->id) ?? [] as $station) {
                if (! isset($machine[$station->id])) {
                    continue;
                }
                $current = $machine[$station->id];
                $hasCollector = true;
                $lastSeen = $lastSeen === null || $current['seenAt']->gt($lastSeen) ? $current['seenAt'] : $lastSeen;

                if (self::isSilent($current, $silentBefore)) {
                    continue; // its last state is no longer believed
                }
                $anyFresh = true;
                $candidates[] = $this->stationCandidate($current, $station->id);
            }

            if ($hasCollector && ! $anyFresh) {
                // "No data since" the last time any of its stations spoke.
                $candidates[] = $this->candidate('unknown', $lastSeen, null, 'machine');
            }

            foreach ($manualStops->get($line->id) ?? [] as $stop) {
                $candidates[] = $this->stopCandidate($stop);
            }

            foreach ($maintenance->get($line->id) ?? [] as $event) {
                $candidates[] = $this->candidate('down', $event->started_at ?? $event->scheduled_at, __('Maintenance'), 'manual', workstationId: $event->workstation_id);
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

            $lineOrders = $orders->get($line->id) ?? collect();
            $order = $lineOrders->firstWhere('status', WorkOrder::STATUS_BLOCKED)
                ?? $lineOrders->firstWhere('status', WorkOrder::STATUS_IN_PROGRESS);

            return [
                'line' => $line,
                'winner' => $winner,
                'has_collector' => $hasCollector,
                'order_id' => $order?->id,
            ];
        })->values()->all();
    }

    /**
     * The dashboard's "Needs action" list: what is wrong on the floor right
     * now, critical before warning, oldest first.
     *
     * Each item carries at most one button, resolved here for this user: an
     * existing page they can open or the existing stop escalation. `action` is
     * null when they may not use either — the row is still listed, the target
     * route enforces access regardless. A type whose module is switched off is
     * not built at all.
     *
     * `total` counts everything; `items` stops at NEEDS_ACTION_LIMIT. A fixed
     * number of queries regardless of how much is wrong.
     *
     * @param  array<int, array<string, mixed>>|null  $resolvedLines  resolveLines(), when the caller already has it
     * @return array{total: int, items: array<int, array{key: string, type: string, severity: string, title: string, description: ?string, since: ?string, action: ?array<string, string>}>}
     */
    public function needsAction(User $user, ?array $resolvedLines = null): array
    {
        $tabs = TabRegistry::accessibleFor($user);
        $enabled = ModuleRegistry::enabled();
        $limit = self::NEEDS_ACTION_LIMIT;

        $items = $this->lineItems($resolvedLines ?? $this->resolveLines(), $user, $tabs);
        $total = count($items);

        if (in_array('materials', $enabled, true)) {
            [$count, $rows] = $this->lowStockItems($tabs, $limit);
            $total += $count;
            array_push($items, ...$rows);

            [$count, $rows] = $this->quarantineItems($tabs, $limit);
            $total += $count;
            array_push($items, ...$rows);
        }

        if (in_array('maintenance', $enabled, true)) {
            [$count, $rows] = $this->failedInspectionItems($user, $limit);
            $total += $count;
            array_push($items, ...$rows);
        }

        if (in_array('quality', $enabled, true)) {
            [$count, $rows] = $this->overdueActionItems($user, $tabs, $limit);
            $total += $count;
            array_push($items, ...$rows);
        }

        usort($items, function (array $a, array $b) {
            $bySeverity = self::SEVERITY_PRIORITY[$b['severity']] <=> self::SEVERITY_PRIORITY[$a['severity']];
            if ($bySeverity !== 0) {
                return $bySeverity;
            }
            $bySince = ($a['since']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['since']?->getTimestamp() ?? PHP_INT_MAX);

            return $bySince !== 0 ? $bySince : strcmp($a['key'], $b['key']);
        });

        return [
            'total' => $total,
            'items' => array_map(
                fn (array $item) => ['since' => $item['since']?->toIso8601String()] + $item,
                array_slice($items, 0, $limit),
            ),
        ];
    }

    /**
     * Down and blocked lines. "Open ticket" is the shift monitor's escalation,
     * offered only where it would succeed — a stop on a station with a work
     * order running when it began, for Admin/Supervisor (EscalateDowntimeRequest)
     * with the Shift Monitor tab. Already escalated → "View ticket" instead.
     * Otherwise the Shift Monitor itself, where the stop can be dealt with.
     *
     * @param  array<int, array<string, mixed>>  $resolved
     * @param  array<int, string>  $tabs
     * @return array<int, array<string, mixed>>
     */
    private function lineItems(array $resolved, User $user, array $tabs): array
    {
        $down = array_filter($resolved, fn (array $r) => $r['winner']['state'] === 'down');
        $blocked = array_filter($resolved, fn (array $r) => $r['winner']['state'] === 'blocked');

        $stops = collect($down)->map(fn (array $r) => $r['winner']['stop'])->filter()->values();
        $workOrders = $this->monitor->escalatableWorkOrders($stops);
        $tickets = $stops->isEmpty() ? collect() : Issue::whereIn('production_downtime_id', $stops->pluck('id'))
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->orderBy('id')
            ->get(['id', 'production_downtime_id'])
            ->keyBy('production_downtime_id');

        $monitorTab = in_array('shift_monitor', $tabs, true);
        $canEscalate = $monitorTab && $user->hasAnyRole(['Admin', 'Supervisor']);

        $items = [];
        foreach ($down as $r) {
            $stop = $r['winner']['stop'];
            $ticket = $stop ? $tickets->get($stop->id) : null;

            $action = null;
            if ($ticket && in_array('quality', $tabs, true)) {
                $action = $this->link('View ticket', route('admin.issues.index', ['issue' => $ticket->id], false));
            } elseif ($canEscalate && $stop && isset($workOrders[$stop->id])) {
                $action = [
                    'kind' => 'escalate',
                    'label' => __('Open ticket'),
                    'href' => route('admin.shift-monitor.escalate', $stop->id, false),
                ];
            } elseif ($monitorTab) {
                $station = $r['winner']['workstation_id'];
                $action = $this->link('Shift Monitor', route('admin.shift-monitor.index', $station ? ['workstation' => $station] : [], false));
            }

            $items[] = $this->item(
                'line_down:'.$r['line']->id, 'line_down', 'critical',
                __(':name is down', ['name' => $r['line']->name]),
                $r['winner']['reason'] ?? __('Reason not recorded'),
                $r['winner']['since'], $action,
            );
        }

        foreach ($blocked as $r) {
            $action = $r['order_id'] && in_array('orders', $tabs, true)
                ? $this->link('Resolve', route('admin.work-orders.show', $r['order_id'], false))
                : null;

            $items[] = $this->item(
                'line_blocked:'.$r['line']->id, 'line_blocked', 'warning',
                __(':name is blocked', ['name' => $r['line']->name]),
                $r['winner']['reason'] ?? __('Reason not recorded'),
                $r['winner']['since'], $action,
            );
        }

        return $items;
    }

    /**
     * Active materials at or under their minimum — the materials widget's rule.
     * There is no "went below minimum" timestamp, so the age is `updated_at`
     * (a stock movement updates it), a heuristic like the work-order `since`.
     *
     * @param  array<int, string>  $tabs
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    private function lowStockItems(array $tabs, int $limit): array
    {
        $query = Material::where('is_active', true)
            ->whereNotNull('min_stock_level')
            ->whereColumn('stock_quantity', '<=', 'min_stock_level');

        $rows = (clone $query)->orderBy('updated_at')->orderBy('id')->limit($limit)
            ->get(['id', 'name', 'stock_quantity', 'min_stock_level', 'unit_of_measure', 'updated_at']);

        $canOpen = in_array('materials', $tabs, true);

        return [(clone $query)->count(), $rows->map(fn (Material $m) => $this->item(
            'material_low:'.$m->id, 'material_low', 'warning',
            __(':material below minimum', ['material' => $m->name]),
            __(':qty of :min :uom', [
                'qty' => self::number($m->stock_quantity),
                'min' => self::number($m->min_stock_level),
                'uom' => $m->unit_of_measure ?? '',
            ]),
            $m->updated_at,
            $canOpen ? $this->link('Resolve', route('admin.materials.show', $m->id, false)) : null,
        ))->all()];
    }

    /**
     * @param  array<int, string>  $tabs
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    private function quarantineItems(array $tabs, int $limit): array
    {
        $query = MaterialLot::where('status', MaterialLot::STATUS_QUARANTINE);

        $rows = (clone $query)->with('material:id,name')
            ->orderByRaw('COALESCE(held_at, updated_at)')->orderBy('id')->limit($limit)
            ->get(['id', 'lot_number', 'material_id', 'hold_reason', 'held_at', 'updated_at']);

        $canOpen = in_array('materials', $tabs, true);

        return [(clone $query)->count(), $rows->map(fn (MaterialLot $lot) => $this->item(
            'lot_quarantined:'.$lot->id, 'lot_quarantined', 'warning',
            __('Lot :lot in quarantine', ['lot' => $lot->lot_number]),
            collect([$lot->material?->name, $lot->hold_reason])->filter()->implode(' · ') ?: null,
            $lot->held_at ?? $lot->updated_at,
            $canOpen ? $this->link('Review', route('admin.material-lots.show', $lot->id, false)) : null,
        ))->all()];
    }

    /**
     * Inbound inspections that failed and still wait for a disposition. Once
     * somebody decided what happens to the lot it no longer needs action.
     *
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    private function failedInspectionItems(User $user, int $limit): array
    {
        $query = Inspection::where('status', Inspection::STATUS_FAIL)
            ->where(fn ($q) => $q->whereNull('disposition')->orWhere('disposition', Inspection::DISPOSITION_PENDING));

        $rows = (clone $query)->with('material:id,name')
            ->orderByRaw('COALESCE(completed_at, started_at)')->orderBy('id')->limit($limit)
            ->get(['id', 'material_id', 'lot_number', 'started_at', 'completed_at']);

        return [(clone $query)->count(), $rows->map(fn (Inspection $i) => $this->item(
            'inspection_failed:'.$i->id, 'inspection_failed', 'critical',
            __('Inbound inspection failed'),
            __(':material · lot :lot', ['material' => $i->material?->name ?? '—', 'lot' => $i->lot_number ?? '—']),
            $i->completed_at ?? $i->started_at,
            $user->can('view', $i) ? $this->link('Review', route('operator.inspections.show', $i->id, false)) : null,
        ))->all()];
    }

    /**
     * Corrective / preventive / containment actions past their due date. There
     * is no issue numbering and no issue page: the button opens the issues list
     * narrowed to the one issue.
     *
     * Issues carry no tenant_id. With a tenant, an action counts only when its
     * issue hangs off a record that does (work order, material or stop); an
     * issue attached to none of them is not attributable and is left out.
     *
     * @param  array<int, string>  $tabs
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    private function overdueActionItems(User $user, array $tabs, int $limit): array
    {
        $query = IssueAction::overdue()->whereHas('issue', function ($issue) use ($user) {
            if ($user->tenant_id) {
                $issue->where(fn ($q) => $q->whereHas('workOrder')->orWhereHas('material')->orWhereHas('productionDowntime'));
            }
        });

        $rows = (clone $query)->orderBy('due_date')->orderBy('id')->limit($limit)
            ->get(['id', 'issue_id', 'type', 'title', 'due_date']);

        $canOpen = in_array('quality', $tabs, true);

        return [(clone $query)->count(), $rows->map(fn (IssueAction $a) => $this->item(
            'action_overdue:'.$a->id, 'action_overdue', 'warning',
            match ($a->type) {
                IssueAction::TYPE_PREVENTIVE => __('Preventive action overdue'),
                IssueAction::TYPE_CONTAINMENT => __('Containment action overdue'),
                default => __('Corrective action overdue'),
            },
            __(':title · Issue #:id · due :date', ['title' => $a->title, 'id' => $a->issue_id, 'date' => $a->due_date->toDateString()]),
            // Overdue from the first day after the due date.
            $a->due_date->copy()->addDay()->startOfDay(),
            $canOpen ? $this->link('Open', route('admin.issues.index', ['issue' => $a->issue_id], false)) : null,
        ))->all()];
    }

    /** @return array<string, mixed> */
    private function item(string $key, string $type, string $severity, string $title, ?string $description, ?Carbon $since, ?array $action): array
    {
        return compact('key', 'type', 'severity', 'title', 'description', 'since', 'action');
    }

    /** @return array{kind: string, label: string, href: string} */
    private function link(string $label, string $href): array
    {
        return ['kind' => 'link', 'label' => __($label), 'href' => $href];
    }

    /** 12.500 → "12.5", 40.000 → "40". */
    private static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }
}
