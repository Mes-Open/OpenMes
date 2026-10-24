<?php

namespace App\Services\Dashboard;

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
 *
 * needsAction() is the dashboard's "Needs action" list. It is NOT the alert
 * badge in the menu (AlertController::totalCount: open issues + overdue work
 * orders + blocked work orders). The two overlap only on blocked work: the
 * badge counts every BLOCKED order, the list one row per blocked LINE. The list
 * adds what the badge does not count — down lines, low stock, failed inbound
 * inspections, quarantined lots, overdue corrective actions — and leaves out
 * plain open issues and overdue orders, which the Alerts page covers.
 */
class PlantOverviewService
{
    private const STATE_PRIORITY = ['down' => 3, 'blocked' => 2, 'running' => 1, 'idle' => 0];

    /** Most "Needs action" rows sent to the browser; `total` still counts all. */
    public const NEEDS_ACTION_LIMIT = 50;

    private const SEVERITY_PRIORITY = ['critical' => 1, 'warning' => 0];

    public function __construct(private ShiftMonitorService $monitor) {}

    /**
     * `needs_action` depends on who is asking (which buttons they may use), so
     * it is only built for a user; without one it is null.
     *
     * @return array{shift: array<string, mixed>, lines: array<int, array<string, mixed>>, needs_action: ?array{total: int, items: array<int, array<string, mixed>>}}
     */
    public function overview(?User $user = null): array
    {
        $resolved = $this->resolveLines();

        return [
            'shift' => $this->currentShift(),
            'lines' => $this->publicLines($resolved),
            'needs_action' => $user ? $this->needsAction($user, $resolved) : null,
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
        return $this->publicLines($this->resolveLines());
    }

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
                $candidates[] = $this->stationCandidate($machine[$station->id], $station->id);
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
    private function stationCandidate(array $current, int $workstationId): array
    {
        $stop = $current['stop'];
        $stopReason = $this->reasonName($stop);
        $needsReason = $stop !== null && ($stop->needs_reason || $stop->reason === null);

        $at = ['stop' => $stop, 'workstationId' => $workstationId];

        return match ($current['state']) {
            WorkstationState::RUNNING => $this->candidate('running', $current['stateSince'], null, 'machine', ...$at),
            WorkstationState::FAULT => $this->candidate('down', $current['since'], $stopReason ?? __('Fault'), 'machine', $needsReason, ...$at),
            WorkstationState::MAINTENANCE => $this->candidate('down', $current['since'], $stopReason ?? __('Maintenance'), 'machine', ...$at),
            WorkstationState::WAITING => $this->candidate('blocked', $current['since'], $stopReason ?? __('Waiting for material'), 'machine', ...$at),
            WorkstationState::STOPPED => $stop && $this->isUnplanned($stop)
                ? $this->candidate('down', $current['since'], $stopReason, 'machine', $needsReason, ...$at)
                : $this->candidate('idle', $current['since'], $stopReason, 'machine', ...$at),
            default => $this->candidate('idle', $current['stateSince'], null, 'machine', ...$at),
        };
    }

    /** A stop reported by hand or against the whole line. */
    private function stopCandidate(ProductionDowntime $stop): array
    {
        $needsReason = $stop->needs_reason || $stop->reason === null;

        $at = ['stop' => $stop, 'workstationId' => $stop->workstation_id];

        return $this->isUnplanned($stop)
            ? $this->candidate('down', $stop->started_at, $this->reasonName($stop), 'manual', $needsReason, ...$at)
            : $this->candidate('idle', $stop->started_at, $this->reasonName($stop), 'manual', ...$at);
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
}
