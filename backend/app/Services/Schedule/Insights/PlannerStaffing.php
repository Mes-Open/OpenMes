<?php

namespace App\Services\Schedule\Insights;

use App\Enums\ScheduledOperationStatus;
use App\Extension\Contracts\ExplainsWorkforceCapacity;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Models\Line;
use App\Models\ScheduledOperation;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Services\Schedule\Finite\ResourceCalendar;
use App\Services\Schedule\Finite\ShiftOccurrences;
use App\Services\Schedule\Finite\StaffingCounter;
use App\Services\Schedule\Finite\Timeline;
use App\Services\Schedule\SchedulePlannerService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The Production Planner's staffing badges: for every line × shift cell of
 * the board, the people available for the line against the people its planned
 * work needs, counted by the same engine as the automatic scheduler
 * ({@see StaffingCounter}) — someone assigned to two lines is one person.
 *
 * Demand is what the board draws. An order the automatic scheduler placed
 * step by step books each committed step on its line for its own window with
 * its own crew size, exactly as the scheduler books existing work. Any other
 * order books every shift cell its blocks cover (primary placement and extra
 * segments, the same day + shift span the weekly view draws) with the crew its
 * routing needs; an order with an exact minute plan books that window. Work on
 * every active line is booked, so a person shared with a line the board's
 * filter hides is still held there.
 *
 * Cells are the board's columns: the distinct active shifts by time window,
 * at most four a day. A cell where no shift runs for the line and nothing is
 * planned has no badge. The board range is extended by a lookahead so the
 * planner can find the next shift with enough people.
 *
 * Read-only. One instance per request: loading the same range again is a
 * no-op until {@see forget()} — called after every write — so the drawer and
 * the badges of one response share a single count.
 */
class PlannerStaffing
{
    /** Days past the visible range searched for a slot with enough people. */
    public const LOOKAHEAD_DAYS = 14;

    /** @var list<array{start: string, end: string, name: string}> board columns, 1-based by index + 1 */
    private array $columns = [];

    /** @var Collection<int, Shift> */
    private Collection $shifts;

    /** @var Collection<int, Line> every active line, by id */
    private Collection $allLines;

    private Timeline $timeline;

    private StaffingCounter $counter;

    /** @var Collection<int, WorkOrder> orders booked, by id */
    private Collection $orders;

    /**
     * order id => what it books: line, window, crew, cell (line|date|col) or null for an exact window.
     *
     * @var array<int, list<array{line_id: int, start: int, end: int, operators: int, skills: list<int>, cells: list<string>, primary: bool}>>
     */
    private array $bookings = [];

    /** @var array<int, array<string, true>> line id => "start|end" minutes of its shift runs */
    private array $runs = [];

    /** @var array<string, array<string, mixed>> memo of computed cells */
    private array $cells = [];

    private Carbon $from;

    /** "from|to" of the loaded range; null when nothing is loaded */
    private ?string $loaded = null;

    private Carbon $to;

    public function __construct(
        private readonly WorkforceCapacityProvider $workforce,
        private readonly SchedulePlannerService $planner,
    ) {}

    /**
     * Load the board's work between two dates (inclusive) plus the lookahead.
     */
    public function load(Carbon $rangeStart, Carbon $rangeEnd): self
    {
        $key = $rangeStart->toDateString().'|'.$rangeEnd->toDateString();
        if ($this->loaded === $key) {
            return $this;
        }
        $this->forget();
        $this->loaded = $key;
        $this->from = $rangeStart->copy()->startOfDay();
        $this->to = $rangeEnd->copy()->startOfDay()->addDays(1 + self::LOOKAHEAD_DAYS);
        $this->shifts = Shift::where('is_active', true)->get();
        $this->columns = $this->shifts->sortBy([['sort_order', 'asc'], ['start_time', 'asc']])
            ->unique(fn (Shift $s) => $s->start_time.'|'.$s->end_time)
            ->take(4)
            ->map(fn (Shift $s) => ['start' => substr((string) $s->start_time, 0, 5), 'end' => substr((string) $s->end_time, 0, 5), 'name' => (string) $s->name])
            ->values()
            ->all();
        $this->allLines = Line::where('is_active', true)->orderBy('name')->get()->keyBy('id');
        $this->timeline = new Timeline;
        $this->counter = new StaffingCounter(
            $this->workforce,
            $this->timeline,
            StaffingCounter::poolFor($this->workforce, $this->allLines->keys()->map(fn ($id) => (int) $id)->all()),
        );

        $min = ResourceCalendar::toMinutes($this->from);
        $max = ResourceCalendar::toMinutes($this->to);
        foreach ($this->allLines->keys() as $lineId) {
            foreach (ShiftOccurrences::occurrences($this->shifts, (int) $lineId, $min, $max) as $run) {
                $this->runs[(int) $lineId][$run['start'].'|'.$run['end']] = true;
            }
        }

        $this->orders = $this->planner->boardOrders($this->allLines->keys(), $this->from, $this->to->copy()->subSecond())->keyBy('id');
        $this->bookOrders();

        return $this;
    }

    /** Drop what was counted: the next load() reads the plan and the people again. */
    public function forget(): void
    {
        $this->loaded = null;
        $this->bookings = [];
        $this->runs = [];
        $this->cells = [];
    }

    /** @return list<array{start: string, end: string, name: string}> */
    public function columns(): array
    {
        return $this->columns;
    }

    public function lines(): Collection
    {
        return $this->allLines;
    }

    public function orders(): Collection
    {
        return $this->orders;
    }

    public function counter(): StaffingCounter
    {
        return $this->counter;
    }

    public function shifts(): Collection
    {
        return $this->shifts;
    }

    /** @return list<array{line_id: int, start: int, end: int, operators: int, skills: list<int>, cells: list<string>, primary: bool}> */
    public function bookingsOf(int $orderId): array
    {
        return $this->bookings[$orderId] ?? [];
    }

    /**
     * The window of one board cell in epoch minutes.
     *
     * @return array{0: int, 1: int}|null
     */
    public function window(string $date, int $col): ?array
    {
        $column = $this->columns[$col - 1] ?? null;
        if ($column === null) {
            return null;
        }
        $day = Carbon::parse($date)->startOfDay();
        $start = $day->copy()->setTimeFromTimeString($column['start']);
        $end = $day->copy()->setTimeFromTimeString($column['end']);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [ResourceCalendar::toMinutes($start), ResourceCalendar::toMinutes($end)];
    }

    /** Whether one of the line's shifts runs in the cell. */
    public function isShift(int $lineId, string $date, int $col): bool
    {
        $window = $this->window($date, $col);

        return $window !== null && isset($this->runs[$lineId][$window[0].'|'.$window[1]]);
    }

    /**
     * One line × shift cell: who is there for the line and what its planned
     * work needs; null where no shift runs and nothing is planned.
     *
     * @return array<string, mixed>|null
     */
    public function cell(int $lineId, string $date, int $col): ?array
    {
        $key = $lineId.'|'.$date.'|'.$col;
        if (array_key_exists($key, $this->cells)) {
            return $this->cells[$key];
        }

        $window = $this->window($date, $col);
        if ($window === null) {
            return $this->cells[$key] = null;
        }
        [$start, $end] = $window;
        $orders = $this->ordersIn($lineId, $start, $end);
        $isShift = isset($this->runs[$lineId][$start.'|'.$end]);
        if (! $isShift && $orders === []) {
            return $this->cells[$key] = null;
        }

        $count = $this->counter->count($lineId, $start, $end);
        $explains = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce : null;
        $absent = $count['tracked'] && $explains !== null
            ? array_values(array_map('intval', $explains->absentWorkerIds($lineId, ResourceCalendar::toCarbon($start), ResourceCalendar::toCarbon($end))))
            : [];

        return $this->cells[$key] = [
            'line_id' => $lineId,
            'date' => $date,
            'shift' => $col,
            'shift_name' => $this->columns[$col - 1]['name'],
            'start' => ResourceCalendar::toCarbon($start)->toIso8601String(),
            'end' => ResourceCalendar::toCarbon($end)->toIso8601String(),
            'start_minute' => $start,
            'end_minute' => $end,
            'is_shift' => $isShift,
            'tracked' => $count['tracked'],
            'present' => $count['present'],
            'available' => $count['available'],
            'needed' => $count['needed'],
            'absent_ids' => $absent,
            'held_elsewhere' => $count['held_elsewhere'],
            'group' => $count['group'] ?? null,
            'order_ids' => $orders,
        ];
    }

    /**
     * Order ids with work on the line overlapping the window.
     *
     * @return list<int>
     */
    public function ordersIn(int $lineId, int $start, int $end): array
    {
        $ids = [];
        foreach ($this->bookings as $orderId => $list) {
            foreach ($list as $b) {
                if ($b['line_id'] === $lineId && $b['start'] < $end && $b['end'] > $start) {
                    $ids[] = $orderId;
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * Book the planned work of every active line onto the timeline.
     */
    private function bookOrders(): void
    {
        if ($this->orders->isEmpty()) {
            return;
        }
        $committed = ScheduledOperation::committed()
            ->whereIn('work_order_id', $this->orders->keys())
            ->whereIn('status', [ScheduledOperationStatus::Scheduled->value, ScheduledOperationStatus::ScheduledLate->value])
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->whereNotNull('line_id')
            ->orderBy('planned_start_at')
            ->get()
            ->groupBy('work_order_id');

        foreach ($this->orders as $order) {
            $ops = $committed->get($order->id);
            if ($ops !== null) {
                foreach ($ops as $op) {
                    $this->book($order, (int) $op->line_id, ResourceCalendar::toMinutes($op->planned_start_at), ResourceCalendar::toMinutes($op->planned_end_at),
                        max(1, (int) $op->required_operators), $this->stepSkills($order, (int) $op->step_number), [], true);
                }

                continue;
            }

            $crew = max(1, (int) round($order->operatorFactor()));
            $skills = $this->orderSkills($order);
            foreach ($this->segments($order) as $segment) {
                if (! $this->allLines->has($segment['line_id'])) {
                    continue;
                }
                if ($segment['exact'] !== null) {
                    [$s, $e] = $segment['exact'];
                    $this->book($order, $segment['line_id'], $s, $e, $crew, $skills, [], $segment['primary']);

                    continue;
                }
                foreach ($segment['cells'] as [$date, $col]) {
                    $window = $this->window($date, $col);
                    if ($window !== null) {
                        $this->book($order, $segment['line_id'], $window[0], $window[1], $crew, $skills, [$segment['line_id'].'|'.$date.'|'.$col], $segment['primary']);
                    }
                }
            }
        }
    }

    /**
     * @param  list<int>  $skills
     * @param  list<string>  $cells
     */
    private function book(WorkOrder $order, int $lineId, int $start, int $end, int $operators, array $skills, array $cells, bool $primary): void
    {
        if ($end <= $start) {
            return;
        }
        $this->timeline->bookLabor($lineId, $start, $end, $operators, $skills);
        $this->bookings[$order->id][] = [
            'line_id' => $lineId, 'start' => $start, 'end' => $end, 'operators' => $operators,
            'skills' => $skills, 'cells' => $cells, 'primary' => $primary,
        ];
    }

    /**
     * The order's segments as the weekly board places them: the primary
     * placement and each extra one, a day + shift span — or, for a primary
     * with an exact minute plan, that window.
     *
     * @return list<array{line_id: int, primary: bool, exact: ?array{0: int, 1: int}, cells: list<array{0: string, 1: int}>}>
     */
    private function segments(WorkOrder $order): array
    {
        $out = [];
        if ($order->line_id !== null) {
            $out[] = $this->segment((int) $order->line_id, $order->planned_start_at?->toDateString() ?? $order->due_date?->toDateString(),
                $order->shift_number, $order->end_date?->toDateString(), $order->end_shift_number, $order, true);
        }
        foreach ($order->extraPlacements as $p) {
            $out[] = $this->segment((int) $p->line_id, $p->due_date?->toDateString(), $p->shift_number ?? $order->shift_number,
                $p->end_date?->toDateString(), $p->end_shift_number, null, false);
        }

        return array_values(array_filter($out));
    }

    private function segment(int $lineId, ?string $date, ?int $shift, ?string $endDate, ?int $endShift, ?WorkOrder $minutePlan, bool $primary): ?array
    {
        $perDay = max(1, count($this->columns));
        if ($minutePlan !== null && $this->isExactPlan($minutePlan)) {
            return ['line_id' => $lineId, 'primary' => $primary, 'cells' => [], 'exact' => [
                ResourceCalendar::toMinutes($minutePlan->planned_start_at), ResourceCalendar::toMinutes($minutePlan->planned_end_at),
            ]];
        }
        if ($date === null || $this->columns === []) {
            return null;
        }
        if (! $shift && $minutePlan?->planned_start_at !== null) {
            $hour = (int) $minutePlan->planned_start_at->format('G');
            $shift = min($perDay, intdiv($hour, intdiv(24, $perDay)) + 1);
        }
        $shift = min(max(1, (int) ($shift ?: 1)), $perDay);
        $lastDate = $date;
        $lastShift = $shift;
        if ($endDate !== null && $endDate >= $date) {
            $lastDate = $endDate;
            $lastShift = min(max(1, (int) ($endShift ?: $shift)), $perDay);
        } elseif ($endShift && $endShift > $shift) {
            $lastShift = min((int) $endShift, $perDay);
        }
        if ($lastDate === $date && $lastShift < $shift) {
            $lastShift = $shift;
        }

        $cells = [];
        $day = Carbon::parse($date);
        for ($i = 0; $i < 62 && $day->toDateString() <= $lastDate; $i++, $day->addDay()) {
            $d = $day->toDateString();
            $first = $d === $date ? $shift : 1;
            $last = $d === $lastDate ? $lastShift : $perDay;
            for ($col = $first; $col <= $last; $col++) {
                $cells[] = [$d, $col];
            }
        }

        return ['line_id' => $lineId, 'primary' => $primary, 'cells' => $cells, 'exact' => null];
    }

    /**
     * A minute plan the planner set on purpose — not the envelope a drop onto
     * a weekly cell writes (shift start to the midnight after the end date).
     */
    private function isExactPlan(WorkOrder $order): bool
    {
        if ($order->planned_start_at === null || $order->planned_end_at === null || $order->planned_end_at->lessThanOrEqualTo($order->planned_start_at)) {
            return false;
        }
        if (! $order->shift_number) {
            return true;
        }

        return $order->planned_end_at->format('H:i:s') !== '00:00:00';
    }

    /** @return list<int> */
    private function stepSkills(WorkOrder $order, int $stepNumber): array
    {
        foreach ($order->process_snapshot['steps'] ?? [] as $step) {
            if ((int) ($step['step_number'] ?? 0) === $stepNumber) {
                return array_values(array_map('intval', $step['required_skill_ids'] ?? []));
            }
        }

        return [];
    }

    /** @return list<int> every skill some step of the order requires */
    public function orderSkills(WorkOrder $order): array
    {
        $ids = [];
        foreach ($order->process_snapshot['steps'] ?? [] as $step) {
            foreach ($step['required_skill_ids'] ?? [] as $id) {
                $ids[(int) $id] = true;
            }
        }
        ksort($ids);

        return array_keys($ids);
    }
}
