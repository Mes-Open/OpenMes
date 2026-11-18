<?php

namespace App\Services\Schedule\Finite;

use App\Enums\ScheduledOperationStatus;
use App\Extension\Contracts\ExplainsWorkforceCapacity;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Extension\Contracts\WorkforceRosterAware;
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
 *     where it finishes first. Ties prefer the order's own line, then — among
 *     its stations — one that only people held back, then the lower station id.
 *  5. On a resource, a slot must fit the shift calendar minus maintenance,
 *     stretched by the line's planning efficiency; must not overlap anything
 *     already booked; and, where the workforce provider tracks people, must
 *     leave enough operators — and enough qualified ones when the step requires
 *     skills — for the whole window. An operation may pause across non-working
 *     time but never interleaves with another job. A window short of people
 *     is retried an hour later, when booked people free up, or at the next
 *     shift start, whichever is first; an absence counts against the shifts
 *     that start on its date, so a night shift belongs to the evening it began.
 *     Someone who staffs several lines is one person: when the provider names
 *     its people ({@see WorkforceRosterAware}), lines sharing anyone are
 *     checked as one pool ({@see SharedOperatorPool}), so work booked on one
 *     line holds its people on the others. A provider that only counts keeps
 *     each line separate. A person is also on one step at a time: with names
 *     and skills known, every step booked in the window is matched to people
 *     holding its skills, so someone holding two skills is not counted for
 *     two concurrent steps. Bookings keep counts, not names, so each stretch of
 *     constant booked load is matched on its own — the same person may cover
 *     different steps in different stretches of one long window, but every
 *     stretch is staffable.
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

    /** People shared between lines, when the provider says who they are. */
    private ?SharedOperatorPool $pool = null;

    /** @var array<int, array<int, true>> line id => skills some operation of this run that may run there requires */
    private array $requiredSkillIds = [];

    /** @var array<int, ?string> skill id => name, looked up once per run */
    private array $skillNameMemo = [];

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
     *     workforce: list<array{line_id: int, line_name: string, tracked: bool, assigned: ?int, respected: bool, single_holder_skills: list<array{skill_id: int, skill_name: ?string, holder_name: ?string}>}>,
     *     staffing: array{shifts: list<array<string, mixed>>, absences: list<array<string, mixed>>},
     *     inputs_fingerprint: string,
     *     staffing_fingerprint: ?string
     * }
     */
    public function schedule(SchedulingRequest $request): array
    {
        $this->calendar = ResourceCalendar::forHorizon($request->start, $request->end);
        $this->timeline = new Timeline;
        $this->respectWorkforce = $request->respectWorkforce;
        $this->requiredSkillIds = [];
        $this->skillNameMemo = [];
        $this->lines = Line::where('is_active', true)->get()->keyBy('id');
        $this->workstations = Workstation::where('is_active', true)
            ->whereIn('line_id', $this->lines->keys())
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        // Only active lines take work, so only they can hold anyone.
        $this->pool = $this->respectWorkforce && $this->workforce instanceof WorkforceRosterAware
            ? new SharedOperatorPool(array_intersect_key($this->workforce->assignedOperatorKeys(), $this->lines->all()))
            : null;

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
            $blocker = null; // the result row that set it

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
                    $orderResults[] = $this->unplaced($order, $demand, $sequence, ScheduledOperationStatus::InfeasiblePredecessor, [
                        $this->predecessorReason($blocker),
                    ]);

                    continue;
                }

                $result = $this->place($order, $demand, $earliest, $horizonEnd, $sequence);
                $orderResults[] = $result;

                if ($result['status']->isScheduled()) {
                    $earliest = max($earliest, ResourceCalendar::toMinutes($result['planned_end_at']));
                } else {
                    $blocked = $result['status'];
                    $blocker = $result;
                }
            }

            $operations = [...$operations, ...$orderResults];
            $summaries[] = $this->summarizeOrder($order, $orderResults, isset($shortages[$order->id]));
        }

        $workforce = $this->workforceByLine($request->start, $request->end);

        return [
            'operations' => $operations,
            'work_orders' => $summaries,
            'resources' => $this->resourceLoads(),
            'counts' => $this->counts($operations),
            'workforce' => $workforce,
            'staffing' => (new StaffingOverview(
                $this->calendar->shifts(), $this->lines, $this->timeline, $this->workforce, $workforce, $horizonStart, $horizonEnd,
            ))->build($operations),
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
                $this->timeline->bookLabor((int) $op->line_id, $start, $end, (int) $op->required_operators, $this->stepSkills($op->workOrder, (int) $op->step_number));
            }
        }

        // A minute plan is one block for the whole order, not step by step, so
        // no one step's skills apply to it: anyone on its line may staff it.
        foreach ($minutePlanned as $order) {
            $start = ResourceCalendar::toMinutes($order->planned_start_at);
            $end = ResourceCalendar::toMinutes($order->planned_end_at);
            $this->timeline->book('line:'.$order->line_id, $start, $end, $this->attributesOf($order));
            $this->timeline->bookLabor((int) $order->line_id, $start, $end, (int) round($order->operatorFactor()));
        }
    }

    /**
     * The skills a routing step requires, from the order's frozen snapshot —
     * the same source {@see OperationProjector} reads — so committed work
     * holds people who hold them, not just anyone on the line.
     *
     * @return list<int>
     */
    private function stepSkills(?WorkOrder $order, int $stepNumber): array
    {
        foreach ($order?->process_snapshot['steps'] ?? [] as $step) {
            if ((int) ($step['step_number'] ?? 0) === $stepNumber) {
                return array_values(array_map('intval', $step['required_skill_ids'] ?? []));
            }
        }

        return [];
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
        foreach ($candidates as $candidate) {
            foreach ($demand->requiredSkillIds as $skillId) {
                $this->requiredSkillIds[$candidate['line_id']][(int) $skillId] = true;
            }
        }
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
            // On a tie between stations of the same line, the one only people
            // held back: its machine was free, so the reason it carries is the
            // one that really bound. Across lines the own line still wins.
            if ($best === null || $slot['end'] < $best['slot']['end']
                || ($slot['end'] === $best['slot']['end'] && $candidate['line_id'] === $best['candidate']['line_id']
                    && $slot['waited'] !== null && $best['slot']['waited'] === null)) {
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
     * A placed slot carries `waited`: the last window it was refused because
     * people it could use were busy on another line — or the qualified people
     * it needs were busy on any work — as long as every later
     * refusal was people busy with other work too; null once a machine
     * conflict, a changeover or a plain lack of people pushed it instead.
     *
     * It also carries `machine_start`, the start the machines alone allowed on
     * this resource, and `first_labor`, the people shortfall that refused it
     * (null when people never refused a window).
     *
     * @return array{ok: true, start: int, end: int, setup: ?int, duration: int, rule_id: ?int, waited: ?array<string, mixed>, machine_start: ?int, first_labor: ?array<string, mixed>}|array{ok: false, failures: list<string>, labor: array<string, array<string, mixed>>}
     */
    private function findSlot(OperationDemand $demand, array $candidate, int $earliest, int $horizonEnd): array
    {
        $windows = $this->calendar->windows($candidate['line_id'], $candidate['workstation']?->id);
        $factor = $this->lines->get($candidate['line_id'])?->planningEfficiencyFactor() ?? 1.0;
        $keys = [$candidate['key'], ...$candidate['related']];
        $failures = [];
        $closest = [];
        $waited = null;
        $laborLines = $this->pool?->groupOf($candidate['line_id']) ?? [$candidate['line_id']];
        // Where the machines alone would have put the step, and the people
        // check that refused that window — see delayedByStaffingReason().
        $machineStart = null;
        $firstLabor = null;

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
                $waited = null;

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
                    $waited = null;

                    continue;
                }
            }

            $labor = $this->laborShortfall($demand, $candidate['line_id'], $t, $end);
            if ($machineStart === null) {
                // Every check above is a machine check, so the first window to
                // reach people is where a people-blind run would have placed it.
                $machineStart = $t;
                $firstLabor = $labor;
            }
            if ($labor !== null) {
                $failures[] = $labor['code'];
                $closest[$labor['code']] = $this->closerShortfall($closest[$labor['code']] ?? null, $labor);
                // People held on another line: that is why it waits. So is a
                // qualified person we can name, busy with other work here.
                // Other people busy on this line keep the reason — typically
                // the shared person moving on to this line's own work. Anything
                // else (an absence, nobody free at all) is a different cause.
                if ($labor['busy_lines'] !== []) {
                    $waited = $labor;
                } elseif ($labor['code'] === 'qualification' && $labor['in_use'] > 0 && $labor['qualified_keys'] !== []) {
                    $waited = $labor;
                } elseif ($labor['in_use'] === 0) {
                    $waited = null;
                }
                // Try again an hour on, when booked people free up, or when the
                // next shift starts — whichever comes first. The shift start
                // keeps the retry on a shift boundary when back-to-back shifts
                // merge into one window, instead of landing mid-shift.
                $t = min(array_filter([
                    $t + self::LABOR_PROBE_MINUTES,
                    $this->timeline->nextLaborReleaseAmong($laborLines, $t),
                    $this->calendar->nextShiftStart($candidate['line_id'], $t),
                ]));

                continue;
            }

            return [
                'ok' => true, 'start' => $t, 'end' => $end, 'setup' => $setup['minutes'], 'duration' => $duration,
                'rule_id' => $setup['rule_id'], 'waited' => $waited, 'machine_start' => $machineStart, 'first_labor' => $firstLabor,
            ];
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
     * ignores people. `busy_lines` names the other lines holding people this
     * line could have used; `qualified_keys` who could take a skilled step,
     * when the provider names them.
     *
     * @return array{code: string, line_id: int, required: int, available: int, in_use: int, start: int, end: int, skill_ids: list<int>, busy_lines: list<array{line_id: int, line_name: string, operators: int}>, qualified_keys: list<string>}|null
     */
    private function laborShortfall(OperationDemand $demand, int $lineId, int $start, int $end): ?array
    {
        if (! $this->respectWorkforce) {
            return null;
        }

        $from = ResourceCalendar::toCarbon($start);
        $to = ResourceCalendar::toCarbon($end);

        $shortfall = fn (string $code, int $available, int $inUse, array $skills, array $busy = [], array $keys = []) => [
            'code' => $code,
            'line_id' => $lineId,
            'required' => $demand->requiredOperators,
            'available' => $available,
            'in_use' => $inUse,
            'start' => $start,
            'end' => $end,
            'skill_ids' => $skills,
            'busy_lines' => $busy,
            'qualified_keys' => $keys,
        ];

        $headcount = null;
        $available = $this->workforce->availableOperators($lineId, $from, $to);
        if ($available !== null) {
            $inUse = $this->timeline->laborInUse($lineId, $start, $end);
            if ($available - $inUse < $demand->requiredOperators) {
                $headcount = $shortfall('workforce_capacity', $available, $inUse, []);
            } else {
                $pooled = $this->pooledShortfall($demand, $lineId, $start, $end, $from, $to);
                if ($pooled !== null) {
                    $headcount = $shortfall('workforce_capacity', $available, $available - $pooled['spare'], [], $pooled['busy_lines']);
                }
            }
        }
        // A skilled step short of people in general is first of all short of
        // the people holding its skills, when those are short too: that is the
        // reason the planner can act on. Otherwise the headcount is.
        if ($headcount !== null && $demand->requiredSkillIds === []) {
            return $headcount;
        }

        if ($demand->requiredSkillIds !== []) {
            $qualified = $this->workforce->qualifiedOperators($lineId, $from, $to, $demand->requiredSkillIds);
            if ($qualified !== null) {
                $inUse = $this->timeline->laborInUse($lineId, $start, $end, $demand->requiredSkillIds);
                $keys = $this->workforce instanceof WorkforceRosterAware
                    ? $this->workforce->qualifiedOperatorKeys($lineId, $from, $to, $demand->requiredSkillIds)
                    : null;
                if ($qualified - $inUse < $demand->requiredOperators) {
                    return $shortfall('qualification', $qualified, $inUse, $demand->requiredSkillIds, [], $keys ?? []);
                }

                // A qualified person shared with another line may be held there.
                $pooled = $keys === null ? null : $this->pooledShortfall($demand, $lineId, $start, $end, $from, $to, $keys);
                if ($pooled !== null) {
                    return $shortfall('qualification', $qualified, $qualified - $pooled['spare'], $demand->requiredSkillIds, $pooled['busy_lines'], $keys);
                }
            }
        }
        if ($headcount !== null) {
            return $headcount;
        }

        // The counts pass; whether the people behind them can be told apart
        // across every step running at once.
        $matched = $this->skillMatchShortfall($demand, $lineId, $start, $end, $from, $to);
        if ($matched !== null) {
            $eligible = count($matched['eligible_keys']);
            $skilled = $demand->requiredSkillIds !== [];

            return $shortfall($skilled ? 'qualification' : 'workforce_capacity', $eligible, $eligible - $matched['spare'],
                $demand->requiredSkillIds, $matched['busy_lines'], $skilled ? $matched['eligible_keys'] : []);
        }

        return null;
    }

    /**
     * The per-count checks passed; whether people can actually be matched to
     * every step booked at once and this one — each person on one step, each
     * step staffed by people holding its skills. Null when they can, or when
     * the provider does not name its people or record their skills (the
     * counts are then all there is).
     *
     * Only needed where skills are involved — the step needs some, or a step
     * booked in the window does; otherwise the headcount checks already are
     * the matching. Each stretch of constant booked load is matched on its
     * own, as {@see pooledShortfall()} does. Who may fill a booked step is read
     * for the new step's window, not the booked step's own: over a multi-day
     * window an absence on a day the booked step does not cover still takes
     * that person out of it, which can leave its places unfilled and overstate
     * what is spare — the same approximation the pooled check makes. Windows
     * are mostly within one shift, where the two coincide.
     *
     * @return array{spare: int, eligible_keys: list<string>, busy_lines: list<array{line_id: int, line_name: string, operators: int}>}|null
     */
    private function skillMatchShortfall(OperationDemand $demand, int $lineId, int $start, int $end, CarbonInterface $from, CarbonInterface $to): ?array
    {
        if ($this->pool === null) {
            return null;
        }

        $roster = $this->workforce;
        $group = $this->pool->groupOf($lineId);
        $skills = $demand->requiredSkillIds;
        if ($skills === [] && ! $this->timeline->hasSkilledLaborBetween($group, $start, $end)) {
            return null;
        }
        // Skills not recorded here: nothing tells people apart beyond the counts.
        if ($roster->qualifiedOperatorKeys($lineId, $from, $to, []) === null) {
            return null;
        }
        $eligible = $skills === []
            ? $roster->presentOperatorKeys($lineId, $from, $to)
            : $roster->qualifiedOperatorKeys($lineId, $from, $to, $skills);
        if ($eligible === null) {
            return null;
        }

        $needed = $demand->requiredOperators;
        $candidates = [];
        $worst = null;
        $seen = [];
        foreach ($this->timeline->skilledLaborSegments($group, $start, $end) as $segment) {
            // The same booked load recurs across a window; it has the same answer.
            $key = implode(',', array_map(fn ($kind, $n) => $kind.':'.$n, array_keys($segment['demand']), $segment['demand']));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            foreach (array_keys($segment['demand']) as $kind) {
                if (! isset($candidates[$kind])) {
                    [$line, $kindSkills] = Timeline::parseSlotKind((string) $kind);
                    $candidates[$kind] = ($kindSkills === [] ? null : $roster->qualifiedOperatorKeys($line, $from, $to, $kindSkills))
                        ?? $roster->presentOperatorKeys($line, $from, $to)
                        ?? [];
                }
            }
            $result = $this->pool->matchSlots($candidates, $segment['demand'], $eligible, $needed);
            if ($worst === null || $result['spare'] < $worst['spare']) {
                $worst = $result;
            }
            if ($worst['spare'] === 0) {
                break; // it cannot get any worse
            }
        }

        // Nothing booked at all: the counts already said it fits.
        if ($worst === null || $worst['spare'] >= $needed) {
            return null;
        }

        $busy = [];
        foreach ($worst['holders'] as $kind) {
            [$line] = Timeline::parseSlotKind((string) $kind);
            if ($line !== $lineId) {
                $busy[$line] = ($busy[$line] ?? 0) + 1;
            }
        }

        return ['spare' => $worst['spare'], 'eligible_keys' => $eligible, 'busy_lines' => $this->busyLines($busy)];
    }

    /**
     * @param  array<int, int>  $busy  line id => operators it holds
     * @return list<array{line_id: int, line_name: string, operators: int}>
     */
    private function busyLines(array $busy): array
    {
        $out = [];
        foreach ($busy as $line => $operators) {
            $out[] = ['line_id' => (int) $line, 'line_name' => (string) ($this->lines->get($line)?->name ?? '#'.$line), 'operators' => $operators];
        }
        usort($out, fn ($a, $b) => [mb_strtolower($a['line_name']), $a['line_id']] <=> [mb_strtolower($b['line_name']), $b['line_id']]);

        return $out;
    }

    /**
     * The line's own check passed; whether people it shares with other lines
     * are held there. Null when they are not, or nothing is shared.
     *
     * Fast paths keep the common case cheap: a line sharing nobody, a window
     * where its own people alone cover the step, or a window with nothing
     * booked on the other lines needs no matching. Each line's presence is
     * read for this same window — with line-specific shift calendars an
     * absence near a shift-date boundary may be attributed a day off for the
     * other lines.
     *
     * With $qualified given, only those people may take the step: the same
     * check for a skilled step, whose qualified people may be held on another
     * line. Booked work still counts by headcount, as the per-line skills
     * check already does.
     *
     * @param  list<string>|null  $qualified  person keys that may take the step; null = anyone present
     * @return array{spare: int, busy_lines: list<array{line_id: int, line_name: string, operators: int}>}|null
     */
    private function pooledShortfall(OperationDemand $demand, int $lineId, int $start, int $end, CarbonInterface $from, CarbonInterface $to, ?array $qualified = null): ?array
    {
        if ($this->pool === null || ! $this->pool->isShared($lineId)) {
            return null;
        }

        $roster = $this->workforce;
        $own = $roster->presentOperatorKeys($lineId, $from, $to);
        if ($own === null) {
            return null;
        }
        // Its own people alone can cover the booked work and the step.
        $needed = $demand->requiredOperators;
        $inUse = $this->timeline->laborInUse($lineId, $start, $end);
        if ($this->pool->privateCount($lineId, $own) - $inUse >= $needed
            && ($qualified === null || $this->pool->privateCount($lineId, $qualified) >= $needed)) {
            return null;
        }

        $group = $this->pool->groupOf($lineId);
        $others = array_values(array_diff($group, [$lineId]));
        if (! $this->timeline->hasLaborBetween($others, $start, $end)) {
            return null;
        }

        $present = [$lineId => $own];
        foreach ($others as $other) {
            $present[$other] = $roster->presentOperatorKeys($other, $from, $to) ?? [];
        }

        $worst = null;
        $seen = [];
        foreach ($this->timeline->laborSegments($group, $start, $end) as $segment) {
            // The same booked load recurs across a window; it has the same answer.
            $key = implode(',', array_map(fn ($line, $n) => $line.':'.$n, array_keys($segment['demand']), $segment['demand']));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result = $this->pool->spare($lineId, $present, $segment['demand'], $needed, $qualified);
            if ($worst === null || $result['spare'] < $worst['spare']) {
                $worst = $result;
            }
            if ($worst['spare'] === 0) {
                break; // it cannot get any worse
            }
        }

        if ($worst === null || $worst['spare'] >= $needed) {
            return null;
        }

        return ['spare' => $worst['spare'], 'busy_lines' => $this->busyLines($worst['busy'])];
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
            'waited' => null,
            'machine_start' => null,
            'first_labor' => null,
        ], $sequence, ['code' => 'in_progress', 'message' => __('Already running — kept where it is.')]);
    }

    /**
     * Record a placement on the timeline and build its result row.
     *
     * @param  array{start: int, end: int, setup: ?int, duration: int, rule_id: ?int, waited: ?array<string, mixed>, machine_start: ?int, first_labor: ?array<string, mixed>}  $slot
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
        if ($slot['waited'] !== null) {
            $reasons[] = $this->waitedReason($slot['waited']);
        }
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

        // Last, so it never displaces the reasons above; the status stays as is.
        if (($slot['first_labor'] ?? null) !== null && $slot['machine_start'] !== null && $slot['start'] > $slot['machine_start']) {
            $reasons[] = $this->delayedByStaffingReason($slot['machine_start'], $slot['start'], $slot['first_labor']);
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
     * A placed step that people pushed past the slot the machines allowed:
     * from where to where, and the people reason for the machine window —
     * worded exactly like an unplaced step's, absent names included.
     *
     * The comparison is on the resource the step landed on. Once people
     * refused the machine slot the delay is theirs, even if a machine
     * conflict further on added to it.
     *
     * @param  array<string, mixed>  $labor  from {@see laborShortfall()}, for the machine window
     * @return array<string, mixed>
     */
    private function delayedByStaffingReason(int $machineStart, int $plannedStart, array $labor): array
    {
        $cause = $this->peopleReason($labor['code'], $labor);
        $from = ResourceCalendar::toCarbon($machineStart);
        $to = ResourceCalendar::toCarbon($plannedStart);

        return [
            'code' => 'delayed_by_staffing',
            'message' => __('Delayed by staffing: :from → :to — :reason', [
                'from' => $from->format('Y-m-d H:i'),
                'to' => $to->format('Y-m-d H:i'),
                'reason' => $cause['message'],
            ]),
            'machine_start' => $from->toIso8601String(),
            'planned_start' => $to->toIso8601String(),
            'delay_minutes' => $plannedStart - $machineStart,
            'line_id' => $cause['line_id'],
            'required' => $cause['required'],
            'available' => $cause['available'],
            'in_use' => $cause['in_use'],
            'window_start' => $cause['window_start'],
            'window_end' => $cause['window_end'],
            'absent_worker_ids' => $cause['absent_worker_ids'],
            'absent_workers' => $cause['absent_workers'],
            'cause' => $cause,
        ];
    }

    /**
     * Why a placed step starts later than it could have: people held on
     * another line, or — on its own line — the few qualified people it needs
     * busy with other work.
     *
     * @param  array<string, mixed>  $waited  from {@see laborShortfall()}
     * @return array<string, mixed>
     */
    private function waitedReason(array $waited): array
    {
        $window = [
            'window_start' => ResourceCalendar::toCarbon($waited['start'])->toIso8601String(),
            'window_end' => ResourceCalendar::toCarbon($waited['end'])->toIso8601String(),
        ];

        if ($waited['busy_lines'] !== [] && ($waited['code'] !== 'qualification' || $waited['qualified_keys'] === [])) {
            return [
                'code' => 'waited_for_operators',
                'message' => __('Waited for operators busy on :lines.', ['lines' => $this->lineNames($waited['busy_lines'])]),
                'line_id' => $waited['line_id'],
                'busy_lines' => $waited['busy_lines'],
            ] + $window;
        }

        $skills = $this->namedSkills($waited['skill_ids']);
        $workers = $this->namedOperators($waited['qualified_keys']);
        $params = [
            'count' => count($workers),
            'skills' => $this->skillList($skills),
            'names' => implode(', ', array_column($workers, 'name')),
            'lines' => $this->lineNames($waited['busy_lines']),
        ];
        // Of them, how many were busy: "all" only when it was every one.
        $busy = min((int) $waited['in_use'], count($workers));
        $params['busy'] = $busy;
        $one = count($workers) === 1;
        $all = $busy >= count($workers);
        $held = $waited['busy_lines'] !== [];
        $message = match (true) {
            ! $all && ! $held => __('Waited: :busy of :count qualified operators for :skills (:names) busy with other work.', $params),
            ! $all => __('Waited: :busy of :count qualified operators for :skills (:names) busy with other work, some on :lines.', $params),
            ! $held && $one => __('Waited: only :count qualified operator for :skills (:names), busy with other work.', $params),
            ! $held => __('Waited: only :count qualified operators for :skills (:names), all busy with other work.', $params),
            $one => __('Waited: only :count qualified operator for :skills (:names), busy with other work on :lines.', $params),
            default => __('Waited: only :count qualified operators for :skills (:names), all busy with other work, some on :lines.', $params),
        };

        return [
            'code' => 'waited_for_qualified_operators',
            'message' => $message,
            'line_id' => $waited['line_id'],
            'busy_lines' => $waited['busy_lines'],
            'available' => count($workers),
            'busy' => $busy,
            'required_skill_ids' => $waited['skill_ids'],
            'required_skills' => $skills,
            'qualified_workers' => $workers,
        ] + $window;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: ?string}>
     */
    private function namedSkills(array $ids): array
    {
        $explains = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce : null;
        $missing = array_values(array_diff(array_unique($ids), array_keys($this->skillNameMemo)));
        if ($missing !== []) {
            $found = $explains?->skillNames($missing) ?? [];
            foreach ($missing as $id) {
                $this->skillNameMemo[$id] = $found[$id] ?? null;
            }
        }

        return array_map(fn (int $id) => ['id' => $id, 'name' => $this->skillNameMemo[$id] ?? null], array_values($ids));
    }

    /** @param  list<array{id: int, name: ?string}>  $skills */
    private function skillList(array $skills): string
    {
        return implode(', ', array_map(fn ($s) => $s['name'] ?? '#'.$s['id'], $skills));
    }

    /**
     * People by key, with their names, sorted by name.
     *
     * @param  list<string>  $keys
     * @return list<array{name: string}>
     */
    private function namedOperators(array $keys): array
    {
        $names = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce->operatorNames($keys) : [];
        $out = array_map(fn (string $key) => ['name' => $names[$key] ?? '#'.substr($key, (int) strpos($key, ':') + 1)], array_values(array_unique($keys)));
        usort($out, fn ($a, $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $reasons
     * @return array<string, mixed>
     */
    /**
     * Why a step after an unplaceable one is not placed either. When the
     * earlier step was short of people, the reason says which step and by how
     * much — that is what the planner has to fix — and carries the earlier
     * step's people reason as `cause`; otherwise it names the step only.
     *
     * @param  array<string, mixed>|null  $blocker  the earlier step's result row
     * @return array<string, mixed>
     */
    private function predecessorReason(?array $blocker): array
    {
        if ($blocker === null) {
            return ['code' => 'predecessor_not_schedulable', 'message' => __('An earlier step of this order could not be scheduled.')];
        }

        $people = collect($blocker['reasons'])->first(fn (array $r) => in_array($r['code'] ?? null, ['workforce_capacity', 'qualification'], true)
            && isset($r['required'], $r['available']));
        $reason = [
            'code' => 'predecessor_not_schedulable',
            'blocking_step_number' => $blocker['step_number'],
            'blocking_step_name' => $blocker['step_name'],
        ];
        $params = ['step' => $blocker['step_number']];

        if ($people === null) {
            return $reason + ['message' => __('Waits for step :step, which could not be scheduled.', $params)];
        }

        $params += ['required' => $people['required'], 'available' => $people['available']];

        return $reason + [
            'message' => $people['code'] === 'qualification'
                ? __('Waits for step :step, which is short of qualified operators (needs :required, :available available).', $params)
                : __('Waits for step :step, which is short of operators (needs :required, :available available).', $params),
            'required' => $people['required'],
            'available' => $people['available'],
            'cause' => $people,
        ];
    }

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
            'busy_lines' => $detail['busy_lines'] ?? [],
        ];

        if ($code === 'qualification') {
            $skills = $this->namedSkills($detail['skill_ids']);
            $params['skills'] = $this->skillList($skills);
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
            // Of those busy, how many other lines hold; the rest work on this one.
            $held = min($detail['in_use'], array_sum(array_column($reason['busy_lines'], 'operators')));
            $lines = $this->lineNames($reason['busy_lines']);
            $message .= ' '.match (true) {
                $held === 0 => __(':count busy with other work.', ['count' => $detail['in_use']]),
                $held === $detail['in_use'] => __(':count busy with other work on :lines.', ['count' => $held, 'lines' => $lines]),
                default => __(':count busy with other work, :held of them on :lines.', ['count' => $detail['in_use'], 'held' => $held, 'lines' => $lines]),
            };
        }

        // Which of the skills is scarcest on the line, and who holds it.
        $holders = $code === 'qualification' && $detail['skill_ids'] !== [] ? $explains?->skillHolders($detail['line_id']) : null;
        if ($holders !== null) {
            $bottleneck = null;
            foreach ($detail['skill_ids'] as $id) {
                $rank = [count($holders[$id] ?? []), $id];
                if ($bottleneck === null || $rank < $bottleneck) {
                    $bottleneck = $rank;
                }
            }
            $skill = $this->namedSkills([$bottleneck[1]])[0];
            $people = $this->namedOperators($holders[$skill['id']] ?? []);
            $reason['bottleneck_skill'] = $skill;
            $reason['skill_holders'] = $people;
            $sentence = ['skill' => $skill['name'] ?? '#'.$skill['id'], 'names' => implode(', ', array_column($people, 'name'))];
            $message .= ' '.match (count($people)) {
                0 => __('Nobody on this line holds :skill.', $sentence),
                1 => __(':skill is held only by :names.', $sentence),
                default => __(':skill is held by :names.', $sentence),
            };
        }

        return ['message' => $message] + $reason;
    }

    /** @param  list<array{line_name: string}>  $busyLines */
    private function lineNames(array $busyLines): string
    {
        return implode(', ', array_column($busyLines, 'line_name'));
    }

    /**
     * Whether each active line's staffing constrains the run: a line nobody is
     * assigned to is scheduled on machine capacity alone, and the planner must
     * be able to see that. Reported even when the run ignores people, flagged
     * `respected: false`.
     *
     * `single_holder_skills` lists, per line, the skills some step of the run
     * that may run on that line requires and only one person assigned there holds — a single point of
     * failure the planner should know about. Skills nobody requires are left
     * out, and so are skills nobody there holds (those steps cannot be placed
     * and say so).
     *
     * @return list<array{line_id: int, line_name: string, tracked: bool, assigned: ?int, respected: bool, single_holder_skills: list<array{skill_id: int, skill_name: ?string, holder_name: ?string}>}>
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
                    'single_holder_skills' => $this->singleHolderSkills($explains, (int) $line->id),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{skill_id: int, skill_name: ?string, holder_name: ?string}> */
    private function singleHolderSkills(?ExplainsWorkforceCapacity $explains, int $lineId): array
    {
        $required = $this->requiredSkillIds[$lineId] ?? [];
        $holders = $required === [] ? null : $explains?->skillHolders($lineId);
        if ($holders === null) {
            return [];
        }

        $single = array_values(array_filter(array_keys($required), fn (int $id) => count($holders[$id] ?? []) === 1));
        $out = [];
        foreach ($this->namedSkills($single) as $skill) {
            $out[] = [
                'skill_id' => $skill['id'],
                'skill_name' => $skill['name'],
                'holder_name' => $this->namedOperators($holders[$skill['id']])[0]['name'],
            ];
        }
        usort($out, fn ($a, $b) => [mb_strtolower((string) $a['skill_name']), $a['skill_id']] <=> [mb_strtolower((string) $b['skill_name']), $b['skill_id']]);

        return $out;
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
