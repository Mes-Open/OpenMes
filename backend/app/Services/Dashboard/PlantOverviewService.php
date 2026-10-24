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

    public function __construct(
        private ShiftMonitorService $monitor,
        private ShiftPlanService $plan,
    ) {}

    /**
     * @return array{shift: array<string, mixed>, lines: array<int, array<string, mixed>>, production: array<string, mixed>}
     */
    public function overview(): array
    {
        return [
            'shift' => $this->currentShift(),
            'lines' => $this->lineStates(),
            // Planned vs actual output of the plant shift, hour by hour.
            'production' => $this->plan->forShift(ShiftWindow::current()),
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
}
