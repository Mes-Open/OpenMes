<?php

namespace App\Services\Schedule\Finite;

use App\Enums\ScheduledOperationStatus;
use App\Extension\Contracts\ExplainsWorkforceCapacity;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\ScheduledOperation;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Material\MaterialAllocationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deterministic finite-capacity scheduler: places each remaining routing step
 * of each order in the earliest slot where every configured constraint holds,
 * or says precisely why it cannot.
 *
 * The heuristic is earliest-feasible-slot, order by order:
 *
 *  1. Orders are taken by priority (highest first), then priority score, then
 *     the earliest due date, then id — so the same data always yields the same
 *     plan.
 *  2. An order whose material the stock cannot cover is not placed at all; its
 *     operations are reported material-constrained. Stock is netted across the
 *     run in scheduling order, so two orders cannot both count on the same
 *     pallet. No readiness date is invented — OpenMES does not know one.
 *  3. Its operations follow routing order: each starts no earlier than its
 *     predecessor ends. A step that cannot be placed makes every later step
 *     infeasible by precedence.
 *  4. Each operation is tried on every eligible resource — the workstation the
 *     routing or batch step fixes, else the active stations of its equipment
 *     class (workstation_type_id), else the order's line as a whole — and lands
 *     where it finishes first. Ties prefer the order's own line, then the
 *     lower station id.
 *  5. On a resource, a slot must fit the shift calendar minus maintenance,
 *     stretched by the line's planning efficiency; must not overlap anything
 *     already booked; and, where the workforce provider tracks people, must
 *     leave enough operators — and enough qualified ones when the step requires
 *     skills — for the whole window. An operation may pause across non-working
 *     time but never interleaves with another job.
 *
 * Nothing is written here: the result is plain data, persisted (or not) by
 * {@see ScheduleSimulationService}. Already-running steps are pinned from the
 * start of the horizon for their remaining estimate; orders outside the run
 * keep their committed operations, or their minute plan, as booked load.
 */
class FiniteScheduler
{
    /** Retry granularity when people, not machines, are the constraint. */
    private const LABOR_PROBE_MINUTES = 60;

    /** Guard against pathological calendars; far above any real search. */
    private const MAX_ATTEMPTS = 20000;

    private ResourceCalendar $calendar;

    private Timeline $timeline;

    /** @var Collection<int, Workstation> */
    private Collection $workstations;

    /** @var Collection<int, Line> */
    private Collection $lines;

    private bool $respectWorkforce = true;

    public function __construct(
        private readonly OperationProjector $projector,
        private readonly WorkforceCapacityProvider $workforce,
        private readonly MaterialAllocationService $materials,
        private readonly ChangeoverResolver $changeovers,
        private readonly WorkforceProvider $people,
    ) {}

    /**
     * @return array{
     *     operations: list<array<string, mixed>>,
     *     work_orders: list<array<string, mixed>>,
     *     resources: list<array<string, mixed>>,
     *     counts: array<string, int>,
     *     workforce: list<array{line_id: int, line_name: string, tracked: bool, assigned: ?int, respected: bool}>,
     *     inputs_fingerprint: string,
     *     staffing_fingerprint: ?string
     * }
     */
    public function schedule(SchedulingRequest $request): array
    {
        $this->calendar = ResourceCalendar::forHorizon($request->start, $request->end);
        $this->timeline = new Timeline;
        $this->respectWorkforce = $request->respectWorkforce;
        $this->lines = Line::where('is_active', true)->get()->keyBy('id');
        $this->workstations = Workstation::where('is_active', true)
            ->whereIn('line_id', $this->lines->keys())
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $horizonStart = ResourceCalendar::toMinutes($request->start);
        $horizonEnd = ResourceCalendar::toMinutes($request->end);

        $orders = $this->ordersToSchedule($request);
        $this->bookCommitments($orders->pluck('id')->all(), $request);

        $shortages = $request->respectMaterial
            ? $this->materials->shortagesForWorkOrders($orders, netted: true)
            : [];

        $operations = [];
        $summaries = [];
        $sequence = 0;

        foreach ($orders as $order) {
            $demands = $this->projector->project($order);
            $orderResults = [];
            $earliest = $horizonStart;
            $blocked = null; // status that stops the rest of the routing

            if (isset($shortages[$order->id])) {
                $blocked = ScheduledOperationStatus::InfeasibleMaterial;
            }

            foreach ($demands as $demand) {
                $sequence++;

                if ($blocked === ScheduledOperationStatus::InfeasibleMaterial) {
                    $orderResults[] = $this->unplaced($order, $demand, $sequence, $blocked, [[
                        'code' => 'material',
                        'message' => __('Stock does not cover this order’s material requirements.'),
                        'shortages' => $shortages[$order->id],
                        // Explicitly unknown: nothing records when material will arrive.
                        'material_ready_at' => null,
                    ]]);

                    continue;
                }

                if ($blocked !== null) {
                    $orderResults[] = $this->unplaced($order, $demand, $sequence, ScheduledOperationStatus::InfeasiblePredecessor, [[
                        'code' => 'predecessor_not_schedulable',
                        'message' => __('An earlier step of this order could not be scheduled.'),
                    ]]);

                    continue;
                }

                $result = $this->place($order, $demand, $earliest, $horizonEnd, $sequence);
                $orderResults[] = $result;

                if ($result['status']->isScheduled()) {
                    $earliest = max($earliest, ResourceCalendar::toMinutes($result['planned_end_at']));
                } else {
                    $blocked = $result['status'];
                }
            }

            $operations = [...$operations, ...$orderResults];
            $summaries[] = $this->summarizeOrder($order, $orderResults, isset($shortages[$order->id]));
        }

        return [
            'operations' => $operations,
            'work_orders' => $summaries,
            'resources' => $this->resourceLoads(),
            'counts' => $this->counts($operations),
            'workforce' => $this->workforceByLine($request->start, $request->end),
            'inputs_fingerprint' => $this->inputsFingerprint($orders->pluck('id')->all(), $request->start, $request->end),
            // A run that ignored people did not read them, so nothing there can make it stale.
            'staffing_fingerprint' => $request->respectWorkforce ? $this->staffingFingerprint($request->start, $request->end) : null,
        ];
    }

    /**
     * Orders the run may place, in scheduling order.
     *
     * @return Collection<int, WorkOrder>
     */
    private function ordersToSchedule(SchedulingRequest $request): Collection
    {
        $query = WorkOrder::with(['batches.steps', 'productType'])
            ->whereIn('status', [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED, WorkOrder::STATUS_IN_PROGRESS])
            ->whereNotNull('process_snapshot');

        if ($request->workOrderIds !== null) {
            $query->whereIn('id', $request->workOrderIds);
        }
        if ($request->scope === SchedulingRequest::SCOPE_UNPLANNED) {
            $query->whereNull('planned_start_at');
        }

        return $query->get()
            ->filter(fn (WorkOrder $o) => ! empty($o->process_snapshot['steps']))
            ->sort(fn (WorkOrder $a, WorkOrder $b) => [
                -(int) $a->priority, -(int) ($a->priority_score ?? 0), $a->due_date?->getTimestamp() ?? PHP_INT_MAX, $a->id,
            ] <=> [
                -(int) $b->priority, -(int) ($b->priority_score ?? 0), $b->due_date?->getTimestamp() ?? PHP_INT_MAX, $b->id,
            ])
            ->values();
    }

    /**
     * What a run must work around: the committed operations of every active
     * order outside the run, and the minute plans of active orders outside it
     * that were never scheduled operation by operation.
     *
     * @param  list<int>  $runIds
     * @return array{0: Collection<int, ScheduledOperation>, 1: Collection<int, WorkOrder>}
     */
    private function commitments(array $runIds, CarbonInterface $start, CarbonInterface $end): array
    {
        $committed = ScheduledOperation::committed()
            ->with('workOrder.productType')
            ->whereNotIn('work_order_id', $runIds)
            ->whereIn('status', [ScheduledOperationStatus::Scheduled->value, ScheduledOperationStatus::ScheduledLate->value])
            ->whereNotNull('planned_start_at')
            ->where('planned_start_at', '<', $end)
            ->where('planned_end_at', '>', $start)
            ->whereHas('workOrder', fn ($q) => $q->whereIn('status', WorkOrder::ACTIVE_STATUSES))
            ->orderBy('id')
            ->get();

        $minutePlanned = WorkOrder::with('productType')
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->whereNotIn('id', [...$runIds, ...$committed->pluck('work_order_id')->unique()->all()])
            ->whereNotNull('line_id')
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->where('planned_start_at', '<', $end)
            ->where('planned_end_at', '>', $start)
            ->orderBy('id')
            ->get();

        return [$committed, $minutePlanned];
    }

    /**
     * A digest of everything a run read besides its own orders: the work it
     * worked around, shifts, maintenance, changeover rules, line efficiency
     * and the active stations. A draft is stale once this differs, whatever
     * changed — a drag on the board, a new maintenance slot, an edited rule.
     * Content, not timestamps, so a change in the same second still counts.
     *
     * @param  list<int>  $runIds
     */
    public function inputsFingerprint(array $runIds, CarbonInterface $start, CarbonInterface $end): string
    {
        [$committed, $minutePlanned] = $this->commitments($runIds, $start, $end);
        $iso = fn ($t) => $t?->toIso8601String();

        return md5(json_encode([
            $committed->map(fn ($op) => [$op->id, $op->work_order_id, $op->workstation_id, $op->line_id, $iso($op->planned_start_at), $iso($op->planned_end_at), $op->status->value, $op->required_operators])->all(),
            $minutePlanned->map(fn ($o) => [$o->id, $o->line_id, $iso($o->planned_start_at), $iso($o->planned_end_at), $o->product_type_id])->all(),
            ResourceCalendar::maintenanceFor($start, $end)->map(fn ($m) => [$m->id, $m->line_id, $m->workstation_id, $iso($m->scheduled_at), $iso($m->scheduled_end_at), $m->status])->all(),
            \App\Models\Shift::where('is_active', true)->orderBy('id')->get()->map(fn ($sh) => [$sh->id, $sh->line_id, $sh->start_time, $sh->end_time, $sh->days_of_week])->all(),
            \App\Models\ChangeoverRule::where('is_active', true)->orderBy('id')->get()->map(fn ($r) => [$r->id, $r->attribute, $r->from_value, $r->to_value, $r->workstation_type_id, $r->workstation_id, $r->setup_minutes])->all(),
            Line::where('is_active', true)->orderBy('id')->get()->map(fn ($l) => [$l->id, (string) $l->planning_efficiency_pct])->all(),
            Workstation::where('is_active', true)->orderBy('id')->get()->map(fn ($w) => [$w->id, $w->line_id, $w->workstation_type_id])->all(),
        ]));
    }

    /**
     * A digest of the staffing a run counted: who staffs each line and who of
     * them is absent on each day of the horizon. Read fresh, never through the
     * capacity provider's per-run cache, so a later call sees later changes.
     */
    public function staffingFingerprint(CarbonInterface $start, CarbonInterface $end): string
    {
        return AssignedStaff::fingerprint($this->people, $start, $end);
    }

    /**
     * Book what the run must work around: the committed operations of every
     * active order outside the run, and — for orders that have a minute plan but
     * were never scheduled operation by operation — their whole line window.
     *
     * @param  list<int>  $runIds
     */
    private function bookCommitments(array $runIds, SchedulingRequest $request): void
    {
        [$committed, $minutePlanned] = $this->commitments($runIds, $request->start, $request->end);

        foreach ($committed as $op) {
            $start = ResourceCalendar::toMinutes($op->planned_start_at);
            $end = ResourceCalendar::toMinutes($op->planned_end_at);
            $key = $op->workstation_id ? 'ws:'.$op->workstation_id : 'line:'.$op->line_id;
            $this->timeline->book($key, $start, $end, $this->attributesOf($op->workOrder));
            if ($op->line_id) {
                $this->timeline->bookLabor((int) $op->line_id, $start, $end, (int) $op->required_operators);
            }
        }

        foreach ($minutePlanned as $order) {
            $start = ResourceCalendar::toMinutes($order->planned_start_at);
            $end = ResourceCalendar::toMinutes($order->planned_end_at);
            $this->timeline->book('line:'.$order->line_id, $start, $end, $this->attributesOf($order));
            $this->timeline->bookLabor((int) $order->line_id, $start, $end, (int) round($order->operatorFactor()));
        }
    }

    /**
     * Place one operation on the best eligible resource.
     *
     * @return array<string, mixed>
     */
    private function place(WorkOrder $order, OperationDemand $demand, int $earliest, int $horizonEnd, int $sequence): array
    {
        if ($demand->standardMinutes() === null) {
            return $this->unplaced($order, $demand, $sequence, ScheduledOperationStatus::InfeasibleUnestimated, [[
                'code' => 'unestimated',
                'message' => __('The routing gives this step no duration — set a standard or estimated time.'),
            ]]);
        }

        $candidates = $this->candidates($order, $demand);
        if ($candidates === []) {
            return $this->unplaced($order, $demand, $sequence, ScheduledOperationStatus::InfeasibleNoEligibleWorkstation, [[
                'code' => 'no_eligible_workstation',
                'message' => $demand->workstationTypeId
                    ? __('No active workstation of the required equipment class.')
                    : __('Neither the step nor the order names a workstation or line to run on.'),
            ]]);
        }

        if ($demand->isStarted()) {
            return $this->pinRunning($order, $demand, $candidates[0], $earliest, $sequence);
        }

        $best = null;
        $failures = [];
        $labor = [];
        foreach ($candidates as $candidate) {
            $slot = $this->findSlot($demand, $candidate, $earliest, $horizonEnd);
            if (! $slot['ok']) {
                $failures = [...$failures, ...$slot['failures']];
                foreach ($slot['labor'] as $code => $detail) {
                    $labor[$code] = $this->closerShortfall($labor[$code] ?? null, $detail);
                }

                continue;
            }
            if ($best === null || $slot['end'] < $best['slot']['end']) {
                $best = ['candidate' => $candidate, 'slot' => $slot];
            }
        }

        if ($best === null) {
            return $this->unplaced($order, $demand, $sequence, $this->failureStatus($failures), $this->failureReasons($failures, $labor));
        }

        return $this->book($order, $demand, $best['candidate'], $best['slot'], $sequence);
    }

    /**
     * Eligible resources for an operation, in tie-break order.
     *
     * @return list<array{key: string, related: list<string>, line_id: int, workstation: ?Workstation, source: string}>
     */
    private function candidates(WorkOrder $order, OperationDemand $demand): array
    {
        if ($demand->fixedWorkstationId !== null) {
            $station = $this->workstations->get($demand->fixedWorkstationId);

            return $station ? [$this->stationCandidate($station, ScheduledOperation::SOURCE_FIXED)] : [];
        }

        if ($demand->workstationTypeId !== null) {
            return $this->workstations
                ->filter(fn (Workstation $w) => (int) $w->workstation_type_id === $demand->workstationTypeId)
                ->sortBy(fn (Workstation $w) => [(int) $w->line_id === (int) $order->line_id ? 0 : 1, $w->id])
                ->map(fn (Workstation $w) => $this->stationCandidate($w, ScheduledOperation::SOURCE_AUTO))
                ->values()
                ->all();
        }

        if ($order->line_id !== null && $this->lines->has($order->line_id)) {
            $lineId = (int) $order->line_id;

            return [[
                'key' => 'line:'.$lineId,
                'related' => $this->workstations->where('line_id', $lineId)->keys()->map(fn ($id) => 'ws:'.$id)->all(),
                'line_id' => $lineId,
                'workstation' => null,
                'source' => ScheduledOperation::SOURCE_LINE,
            ]];
        }

        return [];
    }

    private function stationCandidate(Workstation $station, string $source): array
    {
        return [
            'key' => 'ws:'.$station->id,
            'related' => ['line:'.$station->line_id],
            'line_id' => (int) $station->line_id,
            'workstation' => $station,
            'source' => $source,
        ];
    }

    /**
     * Earliest slot on one resource from $earliest, or the constraints that
     * kept pushing it past the horizon.
     *
     * The people shortfalls come back per code as the window closest to
     * working, so the reason can say how many were missing and when.
     *
     * @return array{ok: true, start: int, end: int, setup: ?int, duration: int, rule_id: ?int}|array{ok: false, failures: list<string>, labor: array<string, array<string, mixed>>}
     */
    private function findSlot(OperationDemand $demand, array $candidate, int $earliest, int $horizonEnd): array
    {
        $windows = $this->calendar->windows($candidate['line_id'], $candidate['workstation']?->id);
        $factor = $this->lines->get($candidate['line_id'])?->planningEfficiencyFactor() ?? 1.0;
        $keys = [$candidate['key'], ...$candidate['related']];
        $failures = [];
        $closest = [];

        $t = $earliest;
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $t = ResourceCalendar::align($windows, $t);
            if ($t === null || $t >= $horizonEnd) {
                break;
            }

            $setup = $this->changeovers->setup(
                $demand,
                $this->timeline->previousAttributes($candidate['key'], $t),
                $candidate['workstation']?->id,
                $candidate['workstation']?->workstation_type_id ? (int) $candidate['workstation']->workstation_type_id : null,
            );
            $work = (int) ($setup['minutes'] ?? 0) + (int) ($demand->runMinutes ?? 0);
            $duration = max(1, (int) ceil($work / $factor));

            $end = ResourceCalendar::consume($windows, $t, $duration);
            if ($end === null) {
                break; // does not fit before the horizon closes
            }

            $conflict = $this->timeline->conflictEnd($keys, $t, $end);
            if ($conflict !== null) {
                $t = $conflict;

                continue;
            }

            // Slotting in before a job already placed makes this operation its
            // new predecessor. If that would lengthen the job's changeover
            // beyond what it reserved, the job no longer fits where it is — so
            // this gap is not usable.
            $successor = $this->timeline->nextBooking($candidate['key'], $end);
            if ($successor !== null && $successor['operation'] !== null) {
                $resetup = (int) ($this->changeovers->setup(
                    $successor['operation'],
                    $demand->attributes,
                    $candidate['workstation']?->id,
                    $candidate['workstation']?->workstation_type_id ? (int) $candidate['workstation']->workstation_type_id : null,
                )['minutes'] ?? 0);
                if ($resetup > $successor['setup']) {
                    $t = $successor['end'];

                    continue;
                }
            }

            $labor = $this->laborShortfall($demand, $candidate['line_id'], $t, $end);
            if ($labor !== null) {
                $failures[] = $labor['code'];
                $closest[$labor['code']] = $this->closerShortfall($closest[$labor['code']] ?? null, $labor);
                $t = min(array_filter([
                    $t + self::LABOR_PROBE_MINUTES,
                    $this->timeline->nextLaborRelease($candidate['line_id'], $t),
                ]));

                continue;
            }

            return ['ok' => true, 'start' => $t, 'end' => $end, 'setup' => $setup['minutes'], 'duration' => $duration, 'rule_id' => $setup['rule_id']];
        }

        $failures[] = 'resource_capacity';

        return ['ok' => false, 'failures' => array_values(array_unique($failures)), 'labor' => $closest];
    }

    /**
     * Of two failing windows, the one nearer to being staffed: the smaller
     * deficit, then the earlier start.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function closerShortfall(?array $current, array $candidate): array
    {
        if ($current === null) {
            return $candidate;
        }
        $deficit = fn (array $d) => $d['available'] - $d['in_use'] - $d['required'];

        return [-$deficit($candidate), $candidate['start']] < [-$deficit($current), $current['start']] ? $candidate : $current;
    }

    /**
     * Which people constraint a window breaks, if any: too few operators on the
     * line, or too few holding the step's skills — with the counts behind it.
     * Untracked (null) never constrains, and neither does anything when the run
     * ignores people.
     *
     * @return array{code: string, line_id: int, required: int, available: int, in_use: int, start: int, end: int, skill_ids: list<int>}|null
     */
    private function laborShortfall(OperationDemand $demand, int $lineId, int $start, int $end): ?array
    {
        if (! $this->respectWorkforce) {
            return null;
        }

        $from = ResourceCalendar::toCarbon($start);
        $to = ResourceCalendar::toCarbon($end);

        $shortfall = fn (string $code, int $available, int $inUse, array $skills) => [
            'code' => $code,
            'line_id' => $lineId,
            'required' => $demand->requiredOperators,
            'available' => $available,
            'in_use' => $inUse,
            'start' => $start,
            'end' => $end,
            'skill_ids' => $skills,
        ];

        $available = $this->workforce->availableOperators($lineId, $from, $to);
        if ($available !== null) {
            $inUse = $this->timeline->laborInUse($lineId, $start, $end);
            if ($available - $inUse < $demand->requiredOperators) {
                return $shortfall('workforce_capacity', $available, $inUse, []);
            }
        }

        if ($demand->requiredSkillIds !== []) {
            $qualified = $this->workforce->qualifiedOperators($lineId, $from, $to, $demand->requiredSkillIds);
            if ($qualified !== null) {
                $inUse = $this->timeline->laborInUse($lineId, $start, $end, $demand->requiredSkillIds);
                if ($qualified - $inUse < $demand->requiredOperators) {
                    return $shortfall('qualification', $qualified, $inUse, $demand->requiredSkillIds);
                }
            }
        }

        return null;
    }

    /**
     * A step already running is not moved: it holds its resource from the
     * start of the horizon for whatever its estimate says is left.
     *
     * @return array<string, mixed>
     */
    private function pinRunning(WorkOrder $order, OperationDemand $demand, array $candidate, int $earliest, int $sequence): array
    {
        $windows = $this->calendar->windows($candidate['line_id'], $candidate['workstation']?->id);
        $elapsed = max(0, $earliest - ResourceCalendar::toMinutes($demand->startedAt));
        $remaining = max(0, (int) $demand->standardMinutes() - $elapsed);
        $start = ResourceCalendar::align($windows, $earliest) ?? $earliest;
        $end = ResourceCalendar::consume($windows, $start, $remaining);

        if ($end === null) {
            return $this->unplaced($order, $demand, $sequence, ScheduledOperationStatus::InfeasibleResourceCapacity, [[
                'code' => 'resource_capacity',
                'message' => __('The running step cannot finish within the horizon.'),
            ]]);
        }

        return $this->book($order, $demand, $candidate, [
            'start' => $remaining > 0 ? $start : $earliest,
            'end' => $remaining > 0 ? $end : $earliest,
            'setup' => null,
            'duration' => $remaining,
            'rule_id' => null,
        ], $sequence, ['code' => 'in_progress', 'message' => __('Already running — kept where it is.')]);
    }

    /**
     * Record a placement on the timeline and build its result row.
     *
     * @param  array{start: int, end: int, setup: ?int, duration: int, rule_id: ?int}  $slot
     * @param  array<string, mixed>|null  $note
     * @return array<string, mixed>
     */
    private function book(WorkOrder $order, OperationDemand $demand, array $candidate, array $slot, int $sequence, ?array $note = null): array
    {
        $this->timeline->book($candidate['key'], $slot['start'], $slot['end'], $demand->attributes, $demand, (int) ($slot['setup'] ?? 0));
        $this->timeline->bookLabor($candidate['line_id'], $slot['start'], $slot['end'], $demand->requiredOperators, $demand->requiredSkillIds);

        $start = ResourceCalendar::toCarbon($slot['start']);
        $end = ResourceCalendar::toCarbon($slot['end']);

        $reasons = $note ? [$note] : [];
        if ($slot['rule_id'] !== null) {
            $reasons[] = [
                'code' => 'changeover',
                'message' => __('Setup time set by a changeover rule.'),
                'changeover_rule_id' => $slot['rule_id'],
            ];
        }

        $status = ScheduledOperationStatus::Scheduled;
        $due = $order->due_date?->copy()->endOfDay();
        if ($due !== null && $end->greaterThan($due)) {
            $status = ScheduledOperationStatus::ScheduledLate;
            $reasons[] = [
                'code' => 'late',
                'message' => __('Finishes after the order’s due date.'),
                'due_date' => $order->due_date->toDateString(),
            ];
        }

        return $this->row($order, $demand, $sequence, $status, $reasons) + [
            'line_id' => $candidate['line_id'],
            'workstation_id' => $candidate['workstation']?->id,
            'resource_source' => $candidate['source'],
            'planned_start_at' => $start,
            'planned_end_at' => $end,
            'duration_minutes' => $slot['duration'],
            'setup_minutes' => $slot['setup'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $reasons
     * @return array<string, mixed>
     */
    private function unplaced(WorkOrder $order, OperationDemand $demand, int $sequence, ScheduledOperationStatus $status, array $reasons): array
    {
        return $this->row($order, $demand, $sequence, $status, $reasons) + [
            'line_id' => $order->line_id,
            'workstation_id' => $demand->fixedWorkstationId,
            'resource_source' => null,
            'planned_start_at' => null,
            'planned_end_at' => null,
            'duration_minutes' => $demand->standardMinutes(),
            'setup_minutes' => $demand->setupMinutes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $reasons
     * @return array<string, mixed>
     */
    private function row(WorkOrder $order, OperationDemand $demand, int $sequence, ScheduledOperationStatus $status, array $reasons): array
    {
        return [
            'work_order_id' => $order->id,
            'order_no' => $order->order_no,
            'step_number' => $demand->stepNumber,
            'step_name' => $demand->name,
            'batch_step_id' => $demand->batchStepId,
            'sequence' => $sequence,
            'workstation_type_id' => $demand->workstationTypeId,
            'required_operators' => $demand->requiredOperators,
            'required_skill_ids' => $demand->requiredSkillIds,
            'status' => $status,
            'reasons' => $reasons,
        ];
    }

    /**
     * The binding constraint across every resource tried: a qualification
     * shortfall is the most specific, then a headcount shortfall, then plain
     * machine capacity.
     *
     * @param  list<string>  $failures
     */
    private function failureStatus(array $failures): ScheduledOperationStatus
    {
        return match (true) {
            in_array('qualification', $failures, true) => ScheduledOperationStatus::InfeasibleQualification,
            in_array('workforce_capacity', $failures, true) => ScheduledOperationStatus::InfeasibleWorkforceCapacity,
            default => ScheduledOperationStatus::InfeasibleResourceCapacity,
        };
    }

    /**
     * @param  list<string>  $failures
     * @param  array<string, array<string, mixed>>  $labor  closest failing window per people code
     * @return list<array<string, mixed>>
     */
    private function failureReasons(array $failures, array $labor = []): array
    {
        $messages = [
            'qualification' => __('Not enough qualified operators are available.'),
            'workforce_capacity' => __('Not enough operators are available.'),
            'resource_capacity' => __('No free capacity on an eligible resource within the horizon.'),
        ];

        return array_values(array_map(
            fn ($code) => isset($labor[$code])
                ? $this->peopleReason($code, $labor[$code])
                : ['code' => $code, 'message' => $messages[$code]],
            array_intersect(array_keys($messages), array_unique($failures)),
        ));
    }

    /**
     * A people reason with the numbers behind it: how many the step needs,
     * how many were there in the window closest to working, how many of them
     * other work held, and who was away. Looked up once per unplaced step.
     *
     * @param  array<string, mixed>  $detail  from {@see laborShortfall()}
     * @return array<string, mixed>
     */
    private function peopleReason(string $code, array $detail): array
    {
        $from = ResourceCalendar::toCarbon($detail['start']);
        $to = ResourceCalendar::toCarbon($detail['end']);
        $explains = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce : null;

        $absentIds = $explains?->absentWorkerIds($detail['line_id'], $from, $to) ?? [];
        $names = $explains?->workerNames($absentIds) ?? [];
        $absent = array_map(fn (int $id) => ['id' => $id, 'name' => $names[$id] ?? null], $absentIds);

        $params = [
            'required' => $detail['required'],
            'available' => $detail['available'],
            'absent' => count($absent),
            'names' => implode(', ', array_map(fn ($w) => $w['name'] ?? '#'.$w['id'], $absent)),
        ];

        $reason = [
            'code' => $code,
            'line_id' => $detail['line_id'],
            'required' => $detail['required'],
            'available' => $detail['available'],
            'in_use' => $detail['in_use'],
            'window_start' => $from->toIso8601String(),
            'window_end' => $to->toIso8601String(),
            'absent_worker_ids' => $absentIds,
            'absent_workers' => $absent,
        ];

        if ($code === 'qualification') {
            $skillNames = $explains?->skillNames($detail['skill_ids']) ?? [];
            $skills = array_map(fn (int $id) => ['id' => $id, 'name' => $skillNames[$id] ?? null], $detail['skill_ids']);
            $params['skills'] = implode(', ', array_map(fn ($s) => $s['name'] ?? '#'.$s['id'], $skills));
            $reason['required_skill_ids'] = $detail['skill_ids'];
            $reason['required_skills'] = $skills;
            $message = $absent === []
                ? __('Needs :required operators qualified in :skills, :available available.', $params)
                : __('Needs :required operators qualified in :skills, :available available (:absent absent: :names).', $params);
        } else {
            $message = $absent === []
                ? __('Needs :required operators, :available available.', $params)
                : __('Needs :required operators, :available available (:absent absent: :names).', $params);
        }

        if ($detail['in_use'] > 0) {
            $message .= ' '.__(':count busy with other work.', ['count' => $detail['in_use']]);
        }

        return ['message' => $message] + $reason;
    }

    /**
     * Whether each active line's staffing constrains the run: a line nobody is
     * assigned to is scheduled on machine capacity alone, and the planner must
     * be able to see that. Reported even when the run ignores people, flagged
     * `respected: false`.
     *
     * @return list<array{line_id: int, line_name: string, tracked: bool, assigned: ?int, respected: bool}>
     */
    private function workforceByLine(CarbonInterface $start, CarbonInterface $end): array
    {
        $explains = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce : null;

        return $this->lines
            ->sortBy(fn (Line $line) => [mb_strtolower((string) $line->name), $line->id])
            ->map(function (Line $line) use ($explains, $start, $end) {
                $assigned = $explains?->assignedCount($line->id);
                $tracked = $explains !== null
                    ? $assigned !== null
                    : $this->workforce->availableOperators($line->id, $start, $end) !== null;

                return [
                    'line_id' => (int) $line->id,
                    'line_name' => (string) $line->name,
                    'tracked' => $tracked,
                    'assigned' => $assigned,
                    'respected' => $this->respectWorkforce,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function summarizeOrder(WorkOrder $order, array $results, bool $materialShort): array
    {
        $placed = array_filter($results, fn ($r) => $r['status']->isScheduled());
        $starts = array_map(fn ($r) => $r['planned_start_at'], $placed);
        $ends = array_map(fn ($r) => $r['planned_end_at'], $placed);
        $feasible = $results !== [] && count($placed) === count($results);

        return [
            'work_order_id' => $order->id,
            'order_no' => $order->order_no,
            'line_id' => $order->line_id,
            'due_date' => $order->due_date?->toDateString(),
            'feasible' => $feasible,
            'late' => collect($results)->contains(fn ($r) => $r['status'] === ScheduledOperationStatus::ScheduledLate),
            'material_constrained' => $materialShort,
            'planned_start_at' => $starts !== [] ? min($starts)->toIso8601String() : null,
            'planned_end_at' => $feasible ? max($ends)->toIso8601String() : null,
            'operation_count' => count($results),
            'fingerprint' => $order->schedulingFingerprint(),
        ];
    }

    /**
     * Load per booked resource across the horizon: booked working minutes
     * against effective available minutes.
     *
     * @return list<array<string, mixed>>
     */
    private function resourceLoads(): array
    {
        $out = [];
        foreach ($this->timeline->bookings() as $key => $slots) {
            [$kind, $id] = explode(':', $key);
            $station = $kind === 'ws' ? $this->workstations->get((int) $id) : null;
            $lineId = $station ? (int) $station->line_id : (int) $id;
            $line = $this->lines->get($lineId);
            if ($line === null) {
                continue;
            }

            $windows = $this->calendar->windows($lineId, $station?->id);
            $busy = array_sum(array_map(fn ($s) => ResourceCalendar::workingMinutesBetween($windows, $s['start'], $s['end']), $slots));
            $available = (int) round($this->calendar->availableMinutes($lineId, $station?->id) * $line->planningEfficiencyFactor());

            $out[] = [
                'key' => $key,
                'type' => $station ? 'workstation' : 'line',
                'id' => (int) $id,
                'name' => $station ? $station->name : $line->name,
                'code' => $station ? $station->code : $line->code,
                'line_id' => $lineId,
                'booked_minutes' => $busy,
                'available_minutes' => $available,
                'load_pct' => $available > 0 ? (int) round($busy / $available * 100) : null,
            ];
        }

        usort($out, fn ($a, $b) => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return array<string, int>
     */
    private function counts(array $operations): array
    {
        $counts = array_fill_keys(array_map(fn ($c) => $c->value, ScheduledOperationStatus::cases()), 0);
        foreach ($operations as $op) {
            $counts[$op['status']->value]++;
        }

        return $counts;
    }

    /** @return array<string, string|null> */
    private function attributesOf(?WorkOrder $order): array
    {
        return [
            'product_type' => $order?->productType?->code,
            'product_category' => $order?->productType?->category,
        ];
    }
}
