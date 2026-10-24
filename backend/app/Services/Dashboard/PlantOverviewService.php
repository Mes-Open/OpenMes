<?php

namespace App\Services\Dashboard;

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
     * @return array{shift: array<string, mixed>, lines: array<int, array<string, mixed>>, downtime: array<string, mixed>}
     */
    public function overview(): array
    {
        return [
            'shift' => $this->currentShift(),
            'lines' => $this->lineStates(),
            'downtime' => $this->downtime(),
        ];
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
        $stop = $current['stop'];
        $stopReason = $this->reasonName($stop);
        $needsReason = $stop !== null && ($stop->needs_reason || $stop->reason === null);

        return match ($current['state']) {
            WorkstationState::RUNNING => $this->candidate('running', $current['stateSince'], null, 'machine'),
            WorkstationState::FAULT => $this->candidate('down', $current['since'], $stopReason ?? __('Fault'), 'machine', $needsReason),
            WorkstationState::MAINTENANCE => $this->candidate('down', $current['since'], $stopReason ?? __('Maintenance'), 'machine'),
            WorkstationState::WAITING => $this->candidate('blocked', $current['since'], $stopReason ?? __('Waiting for material'), 'machine'),
            WorkstationState::STOPPED => $stop && $this->isUnplanned($stop)
                ? $this->candidate('down', $current['since'], $stopReason, 'machine', $needsReason)
                : $this->candidate('idle', $current['since'], $stopReason, 'machine'),
            default => $this->candidate('idle', $current['stateSince'], null, 'machine'),
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
     * Downtime of the plant shift running now, grouped by reason.
     *
     * Uses the same window and overlap rule as Plant Board / Shift Monitor
     * (ShiftWindow::current(), `started_at < end AND (ended_at IS NULL OR
     * ended_at > start)`): each stop is clipped to the part of the window that
     * has already happened, an open stop running to now. Line-level stops
     * reported by hand count too.
     *
     * `planned_minutes` is the net planned time *so far* — active lines ×
     * elapsed shift minutes, minus planned-kind stops — the same net the OEE
     * availability uses (shift time − DowntimeService::getPlannedMinutes), but
     * on elapsed rather than whole-shift time because the numerator is also
     * "so far". `percent_of_planned` is the loss (unplanned, changeover and
     * unclassified stops) over that; planned stops appear in the total and the
     * reason list but are not a loss.
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
        $plannedSeconds = 0;
        $lossSeconds = 0;
        $totalSeconds = 0;
        $linesHit = [];

        foreach ($stops as $stop) {
            $lineId = ($stop->workstation_id ? $stationLine->get($stop->workstation_id) : null) ?? $stop->line_id;
            if (! $lineLabel->has($lineId)) {
                continue;
            }

            $seconds = $this->clippedSeconds($stop, $window->start, $cap);
            if ($seconds <= 0) {
                continue;
            }

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
                $plannedSeconds += $seconds;
            } else {
                $lossSeconds += $seconds;
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
        $plannedTime = $lines->count() * $elapsedSeconds - $plannedSeconds;
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

    /** Seconds of a stop inside [$from, $to], an open stop running to $to. */
    private function clippedSeconds(ProductionDowntime $stop, Carbon $from, Carbon $to): int
    {
        $start = max($stop->started_at->getTimestamp(), $from->getTimestamp());
        $end = min(($stop->ended_at ?? $to)->getTimestamp(), $to->getTimestamp());

        return max(0, $end - $start);
    }
}
