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
 */
class PlantOverviewService
{
    private const STATE_PRIORITY = ['down' => 4, 'blocked' => 3, 'running' => 2, 'idle' => 1, 'unknown' => 0];

    /**
     * A machine-reported state not refreshed for longer than this is no longer
     * believed. No per-station collector setting exists to borrow (the daemon
     * heartbeat and the operator-device window are seconds, per connection or
     * device), so it is a constant: well above the 60 s signal touch
     * (WorkstationStateMachine::SIGNAL_TOUCH_SECONDS) plus a poll period, and
     * above the dashboard's own 60 s refresh, so a healthy line never flickers.
     */
    public const NO_SIGNAL_AFTER_MINUTES = 10;

    public function __construct(private ShiftMonitorService $monitor) {}

    /**
     * @return array{shift: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    public function overview(): array
    {
        return [
            'shift' => $this->currentShift(),
            'lines' => $this->lineStates(),
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
     * @return array<int, array{id: int, name: string, state: 'running'|'down'|'blocked'|'idle'|'unknown', since: ?string, reason: ?string, needs_reason: bool, source: string, has_collector: bool}>
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

        // Every open stop on these lines, read once: the station ones feed the
        // machine states, the rest are reported by hand.
        $openStops = ProductionDowntime::with('reason')
            ->where(fn ($q) => $q->whereIn('line_id', $lineIds)->orWhereIn('workstation_id', $stations->pluck('id')))
            ->whereNull('ended_at')
            ->orderBy('started_at')
            ->get();

        $machine = $this->monitor->currentStates($stations, $openStops);
        $silentBefore = Carbon::now()->subMinutes(self::NO_SIGNAL_AFTER_MINUTES);

        // Stops not already explained by a fresh station's state: line-level
        // ones, ones on a station with no collector, and ones a person logged
        // or classified on a station whose collector went silent (a broken or
        // powered-off machine is exactly when that happens).
        $manualStops = $openStops
            ->reject(function (ProductionDowntime $d) use ($machine, $silentBefore) {
                $current = $d->workstation_id ? ($machine[$d->workstation_id] ?? null) : null;
                if ($current === null) {
                    return false;
                }

                return ! self::isSilent($current, $silentBefore) || $this->isMachinePlaceholder($d);
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
                $candidates[] = $this->stationCandidate($current);
            }

            if ($hasCollector && ! $anyFresh) {
                // "No data since" the last time any of its stations spoke.
                $candidates[] = $this->candidate('unknown', $lastSeen, null, 'machine');
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
    private function stationCandidate(array $current): array
    {
        $stops = $current['stops'];
        $stopReason = $stops->map(fn (ProductionDowntime $d) => $this->reasonName($d))->filter()->first();
        $needsReason = $stops->contains(fn (ProductionDowntime $d) => $this->isPlaceholder($d));

        return match ($current['state']) {
            WorkstationState::RUNNING => $this->candidate('running', $current['stateSince'], null, 'machine'),
            WorkstationState::FAULT => $this->candidate('down', $current['since'], $stopReason ?? __('Fault'), 'machine', $needsReason),
            WorkstationState::MAINTENANCE => $this->candidate('down', $current['since'], $stopReason ?? __('Maintenance'), 'machine', $needsReason),
            WorkstationState::WAITING => $this->candidate('blocked', $current['since'], $stopReason ?? __('Waiting for material'), 'machine', $needsReason),
            WorkstationState::STOPPED => $stops->contains(fn (ProductionDowntime $d) => $this->isUnplanned($d))
                ? $this->candidate('down', $current['since'], $stopReason, 'machine', $needsReason)
                : $this->candidate('idle', $current['since'], $stopReason, 'machine'),
            default => $this->candidate('idle', $current['stateSince'], null, 'machine'),
        };
    }

    /** A stop reported by hand or against the whole line. */
    private function stopCandidate(ProductionDowntime $stop): array
    {
        return $this->isUnplanned($stop)
            ? $this->candidate('down', $stop->started_at, $this->reasonName($stop), 'manual', $this->isPlaceholder($stop))
            : $this->candidate('idle', $stop->started_at, $this->reasonName($stop), 'manual');
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
