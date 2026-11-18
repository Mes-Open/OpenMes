<?php

namespace App\Services\Schedule\Insights;

use App\Extension\Contracts\ExplainsWorkforceCapacity;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Extension\Contracts\WorkforceProvider;
use App\Models\Line;
use App\Models\PlanningInsightAcknowledgement;
use App\Models\ScheduleChangeLog;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStaffingSnapshot;
use App\Services\Line\LineOperatorAssignment;
use App\Services\Schedule\Finite\ResourceCalendar;
use App\Services\Schedule\Finite\ShiftOccurrences;
use App\Services\Schedule\SchedulePlannerService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Production Planner insights: the people problems of the visible plan, each
 * explained and with actions that fix it, computed server-side in one place.
 *
 *  a) short_shift     — a line × shift needs more people than it has
 *                       (who is absent, when the provider says);
 *  b) shared_operator — the line's own people would do, but someone it shares
 *                       with another line is booked there at the same time;
 *  c) single_skill    — planned work needs a skill only one person on the line
 *                       holds (only when the provider records skills);
 *  d) untracked_line  — work planned on a line nobody is assigned to;
 *  e) staffing_changed — an order lost people since it was planned (an
 *                       operator unassigned, a new absence), from the
 *                       snapshot taken at planning time ({@see StaffingSnapshots}).
 *
 * Everything people-related comes through the capacity provider contracts, so
 * with nothing but core installed absences simply never appear and skill
 * insights are absent — nothing fails.
 *
 * Actions are recomputed on the server when they are applied: the client names
 * an insight and an order or person, never a target slot, so a stale drawer
 * cannot move an order into a shift that has since filled up.
 */
class PlanningInsights
{
    public const TYPES = ['short_shift', 'shared_operator', 'single_skill', 'untracked_line', 'staffing_changed'];

    private const SEVERITY_RANK = ['bad' => 0, 'warn' => 1, 'info' => 2];

    private Carbon $from;

    private Carbon $to;

    /** @var list<string> visible dates */
    private array $days = [];

    /**
     * Every date of the ISO weeks the visible dates fall in. Insights that
     * span a period (untracked line, single-skill) are keyed and signed per
     * line and ISO week over these, so the weekly and the daily view — and
     * any day of the week — name the same insight with the same facts.
     *
     * @var list<string>
     */
    private array $weekDays = [];

    /** @var array<int, bool> line id => provider records skills there */
    private array $skillLines = [];

    private ?bool $skillsTracked = null;

    /** "from|to|line" of the loaded board; null after a write */
    private ?string $loaded = null;

    /** @var \Illuminate\Support\Collection<int, User>|null Operator accounts with their lines, read once */
    private ?\Illuminate\Support\Collection $operatorAccounts = null;

    /** @var array<string, array<int, true>> date => absent worker ids */
    private array $absentOn = [];

    /** @var array<int, array<int, list<string>>|null> line id => skill holders */
    private array $holders = [];

    /** @var list<int> visible line ids */
    private array $lineIds = [];

    /** @var array<int, ?string> worker id => name */
    private array $workerNames = [];

    /** @var array<string, string> person key => name */
    private array $keyNames = [];

    /** @var list<array<string, mixed>>|null */
    private ?array $computed = null;

    public function __construct(
        private readonly WorkforceCapacityProvider $workforce,
        private readonly WorkforceProvider $people,
        private readonly PlannerStaffing $staffing,
        private readonly StaffingSnapshots $snapshots,
        private readonly SchedulePlannerService $planner,
        private readonly LineOperatorAssignment $assignment,
    ) {}

    /**
     * The dates the board shows for a view: a week for the weekly view, the
     * day for the daily one. Null for views without line × shift cells.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function rangeFor(?string $viewMode, ?string $startDate): ?array
    {
        $start = $startDate ? Carbon::parse($startDate) : now();

        return match ($viewMode ?: 'weekly') {
            'daily', 'hourly' => [$start->copy()->startOfDay(), $start->copy()->startOfDay()],
            'weekly' => [$start->copy()->startOfWeek(), $start->copy()->startOfWeek()->addDays(6)],
            default => null,
        };
    }

    public function load(Carbon $from, Carbon $to, ?int $lineId = null): self
    {
        $key = $from->toDateString().'|'.$to->toDateString().'|'.$lineId;
        if ($this->loaded === $key) {
            return $this;
        }
        $this->loaded = $key;
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->startOfDay();
        $this->days = [];
        for ($d = $this->from->copy(); $d->lte($this->to); $d->addDay()) {
            $this->days[] = $d->toDateString();
        }
        $weekStart = $this->from->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $this->to->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
        $this->weekDays = [];
        for ($d = $weekStart->copy(); $d->lte($weekEnd); $d->addDay()) {
            $this->weekDays[] = $d->toDateString();
        }
        $this->staffing->load($weekStart, $this->to);
        $this->lineIds = $this->staffing->lines()->keys()
            ->filter(fn ($id) => $lineId === null || (int) $id === $lineId)
            ->map(fn ($id) => (int) $id)->values()->all();
        $this->computed = null;
        $this->forgetPeople();

        return $this;
    }

    /** After a write: the next load() counts the plan and the people afresh. */
    private function invalidate(): void
    {
        $this->loaded = null;
        $this->staffing->forget();
        $this->computed = null;
        $this->forgetPeople();
    }

    private function forgetPeople(): void
    {
        $this->skillsTracked = null;
        $this->operatorAccounts = null;
        $this->absentOn = [];
        $this->holders = [];
        $this->workerNames = [];
        $this->keyNames = [];
    }

    // ── Staffing badges ─────────────────────────────────────────────────────

    /**
     * The badges of every visible cell and the absences per day.
     *
     * @return array{columns: list<array<string, string>>, cells: list<array<string, mixed>>, days: array<string, array{absent: int, names: list<string>}>, tracks_absences: bool}
     */
    public function board(): array
    {
        $cells = [];
        $absentByDay = [];
        foreach ($this->lineIds as $lineId) {
            foreach ($this->days as $date) {
                foreach (array_keys($this->staffing->columns()) as $i) {
                    $cell = $this->staffing->cell($lineId, $date, $i + 1);
                    if ($cell === null || (! $cell['is_shift'] && $cell['needed'] === 0)) {
                        continue;
                    }
                    foreach ($cell['absent_ids'] as $id) {
                        $absentByDay[$date][$id] = true;
                    }
                    $cells[] = $this->presentCell($cell);
                }
            }
        }

        $days = [];
        foreach ($absentByDay as $date => $ids) {
            $days[$date] = ['absent' => count($ids), 'names' => array_values(array_map(fn ($id) => $this->workerName($id), array_keys($ids)))];
        }

        return [
            'columns' => $this->staffing->columns(),
            'cells' => $cells,
            'days' => $days,
            'tracks_absences' => $this->workforce instanceof ExplainsWorkforceCapacity,
        ];
    }

    /** @return array<string, mixed> */
    private function presentCell(array $cell): array
    {
        return [
            'line_id' => $cell['line_id'],
            'date' => $cell['date'],
            'shift' => $cell['shift'],
            'tracked' => $cell['tracked'],
            'present' => $cell['present'],
            'available' => $cell['available'],
            'needed' => $cell['needed'],
            'absent' => array_map(fn ($id) => $this->workerName($id), $cell['absent_ids']),
            'held_elsewhere' => $this->heldElsewhere($cell),
            'order_ids' => $cell['order_ids'],
            'short' => $cell['tracked'] && $cell['needed'] > 0 && $cell['available'] < $cell['needed'],
        ];
    }

    /** @return list<array{line_id: int, line_name: string, names: list<string>}> */
    private function heldElsewhere(array $cell): array
    {
        $out = [];
        foreach ($cell['held_elsewhere'] as $line => $keys) {
            $out[] = ['line_id' => (int) $line, 'line_name' => $this->lineName((int) $line), 'names' => array_map(fn ($k) => $this->keyName($k), $keys)];
        }

        return $out;
    }

    // ── Insights ────────────────────────────────────────────────────────────

    /**
     * The drawer: open insights, what was resolved today, and the counts.
     *
     * @return array<string, mixed>
     */
    public function drawer(): array
    {
        $all = $this->compute();
        $acks = PlanningInsightAcknowledgement::with('acknowledgedBy:id,name')
            ->whereIn('insight_key', array_column($all, 'key'))
            ->get()->keyBy('insight_key');

        $open = array_values(array_filter($all, fn (array $i) => ($acks->get($i['key'])?->signature) !== $i['signature']));
        $today = PlanningInsightAcknowledgement::with('acknowledgedBy:id,name')
            ->where('created_at', '>=', now()->startOfDay())
            ->latest('id')
            ->get();

        return [
            'generated_at' => now()->toIso8601String(),
            'range' => ['start' => $this->from->toDateString(), 'end' => $this->to->toDateString()],
            'summary' => [
                'needs_action' => count(array_filter($open, fn ($i) => $i['severity'] === 'bad')),
                'to_watch' => count(array_filter($open, fn ($i) => $i['severity'] !== 'bad')),
                'resolved' => $today->count(),
            ],
            'insights' => $open,
            'resolved' => $today->map(fn (PlanningInsightAcknowledgement $a) => [
                'id' => $a->id,
                'insight_key' => $a->insight_key,
                'type' => $a->insight_type,
                'category' => $this->categoryOf($a->insight_type),
                'action' => $a->action,
                'title' => $a->title,
                'summary' => $a->summary,
                'by' => $a->acknowledgedBy?->name,
                'at' => $a->created_at?->toIso8601String(),
            ])->values()->all(),
            'provider' => [
                'absences' => $this->workforce instanceof ExplainsWorkforceCapacity,
                'skills' => $this->skillsTracked(),
            ],
        ];
    }

    /**
     * One insight as it stands now, open or acknowledged.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->compute() as $insight) {
            if ($insight['key'] === $key) {
                return $insight;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function compute(): array
    {
        if ($this->computed !== null) {
            return $this->computed;
        }

        $out = [];
        $untracked = [];
        $skillWork = [];
        $visibleOrders = [];
        $visible = array_flip($this->days);
        foreach ($this->lineIds as $lineId) {
            foreach ($this->weekDays as $date) {
                $week = self::weekOf($date);
                foreach (array_keys($this->staffing->columns()) as $i) {
                    $cell = $this->staffing->cell($lineId, $date, $i + 1);
                    if ($cell === null || $cell['needed'] === 0 || $cell['order_ids'] === []) {
                        continue;
                    }
                    $shown = isset($visible[$date]);
                    if (! $cell['tracked']) {
                        $untracked[$lineId][$week]['cells'][] = $cell;
                        $untracked[$lineId][$week]['visible'] = ($untracked[$lineId][$week]['visible'] ?? false) || $shown;
                        foreach ($cell['order_ids'] as $id) {
                            $untracked[$lineId][$week]['orders'][$id] = true;
                        }

                        continue;
                    }
                    $skillWork[$lineId][$week][] = $cell;
                    if (! $shown) {
                        continue;
                    }
                    foreach ($cell['order_ids'] as $id) {
                        $visibleOrders[$id] = true;
                    }
                    if ($cell['available'] < $cell['needed']) {
                        $out[] = $this->shortInsight($cell);
                    }
                }
            }
        }

        foreach ($untracked as $lineId => $weeks) {
            foreach ($weeks as $week => $data) {
                if ($data['visible']) {
                    $out[] = $this->untrackedInsight($lineId, $week, $data['cells'], array_keys($data['orders']));
                }
            }
        }
        foreach ($skillWork as $lineId => $weeks) {
            foreach ($weeks as $week => $cells) {
                foreach ($this->singleSkillInsights($lineId, $week, $cells) as $insight) {
                    $out[] = $insight;
                }
            }
        }
        foreach ($this->changedInsights(array_keys($visibleOrders)) as $insight) {
            $out[] = $insight;
        }

        usort($out, fn ($a, $b) => [self::SEVERITY_RANK[$a['severity']], $a['sort'], $a['key']] <=> [self::SEVERITY_RANK[$b['severity']], $b['sort'], $b['key']]);

        return $this->computed = array_map(function (array $i) {
            unset($i['sort']);

            return $i;
        }, $out);
    }

    /**
     * a) or b): a tracked cell whose work needs more people than it has.
     *
     * @return array<string, mixed>
     */
    private function shortInsight(array $cell): array
    {
        $lineId = $cell['line_id'];
        $line = $this->lineName($lineId);
        $slot = $this->slotLabel($cell);
        $held = $this->heldElsewhere($cell);
        $absent = array_map(fn ($id) => $this->workerName($id), $cell['absent_ids']);
        $shared = $cell['present'] >= $cell['needed'] && $held !== [];
        $missing = $cell['needed'] - $cell['available'];

        $affected = [];
        $actions = [];
        $late = 0;
        foreach ($cell['order_ids'] as $orderId) {
            $order = $this->staffing->orders()->get($orderId);
            if ($order === null) {
                continue;
            }
            $move = $this->moveTarget($order, $cell);
            $entry = $this->affectedEntry($order, $move);
            $late += $entry['late'] ? 1 : 0;
            $affected[] = $entry;
            if ($move !== null) {
                $actions[] = [
                    'kind' => 'move',
                    'work_order_id' => $order->id,
                    'label' => __('Move :order to :slot', ['order' => $order->order_no, 'slot' => $move['label']]),
                    'effect' => ($move['spare'] === 0 ? __('Exactly enough people there.') : __(':spare spare there.', ['spare' => $move['spare']]))
                        .' '.($move['late'] ? __('It would finish after its due date.') : __('It stays on time.')),
                    'score' => $move['late'] ? 1 : 3,
                ];
            }
        }

        foreach ($this->substitutes($cell, $missing) as $candidate) {
            $actions[] = $candidate;
        }
        if ($this->hasCommitted($cell['order_ids'])) {
            $actions[] = ['kind' => 'link', 'href' => '/admin/schedule/auto', 'label' => __('Open in Automatic scheduling'),
                'effect' => __('Plan again around the people who are there.'), 'score' => 0];
        } else {
            $actions[] = ['kind' => 'link', 'href' => '/admin/schedule/auto', 'label' => __('Open in Automatic scheduling'),
                'effect' => __('Let the scheduler place the work where the people are.'), 'score' => 0];
        }
        $actions[] = ['kind' => 'acknowledge', 'label' => __('Acknowledge'), 'effect' => __('Keep the plan as it is.'), 'score' => -1];

        $why = $shared
            ? __(count($held[0]['names']) === 1
                ? ':names is booked on :other at that time, so :line has :available of the :needed people its work needs.'
                : ':names are booked on :other at that time, so :line has :available of the :needed people its work needs.', [
                    'names' => implode(', ', $held[0]['names']), 'other' => $held[0]['line_name'], 'line' => $line,
                    'available' => $cell['available'], 'needed' => $cell['needed'],
                ])
            : __('The plan needs :needed operators on :line, :slot. :available are available.', [
                'needed' => $cell['needed'], 'line' => $line, 'slot' => $slot, 'available' => $cell['available'],
            ]);
        $group = $cell['group'] ?? null;
        if ($group !== null && count($group['line_ids']) > 1) {
            $why .= ' '.__(':lines need :needed people between them at that time and have :available, so one of them comes up short.', [
                'lines' => implode(', ', array_map(fn ($id) => $this->lineName((int) $id), $group['line_ids'])),
                'needed' => $group['needed'], 'available' => $group['available'],
            ]);
        }
        if (! $shared && $absent !== []) {
            $why .= ' '.__('Absent: :names.', ['names' => implode(', ', $absent)]);
        }
        if (! $shared && $held !== []) {
            foreach ($held as $h) {
                $why .= ' '.__(':names booked on :line.', ['names' => implode(', ', $h['names']), 'line' => $h['line_name']]);
            }
        }

        $substitutes = count(array_filter($actions, fn ($a) => $a['kind'] === 'substitute'));
        $key = ($shared ? 'shared:' : 'short:').$lineId.':'.$cell['date'].':'.$cell['shift'];

        return [
            'key' => $key,
            'type' => $shared ? 'shared_operator' : 'short_shift',
            'category' => 'people',
            'severity' => $shared && $late === 0 ? 'warn' : 'bad',
            'title' => $shared
                ? __('Shared operator holds back :line on :slot', ['line' => $line, 'slot' => $slot])
                : __(':line short on :slot', ['line' => $line, 'slot' => $slot]),
            'meta' => $shared
                ? (count($held[0]['names']) === 1
                    ? __(':names also works on :other', ['names' => $held[0]['names'][0], 'other' => $held[0]['line_name']])
                    : __(':names also work on :other', ['names' => implode(', ', $held[0]['names']), 'other' => $held[0]['line_name']]))
                : ($absent !== [] ? __('Absent: :names', ['names' => implode(', ', $absent)]) : __('Needs :needed, :available available', ['needed' => $cell['needed'], 'available' => $cell['available']])),
            'impact' => trans_choice(':count order|:count orders', count($affected), ['count' => count($affected)])
                .($late > 0 ? ' · '.__(':count late', ['count' => $late]) : ''),
            'why' => $why,
            'numbers' => ['needed' => $cell['needed'], 'available' => $cell['available'], 'present' => $cell['present'], 'spare_elsewhere' => $substitutes],
            'affected' => $affected,
            'actions' => $this->rank($actions),
            'cells' => [['line_id' => $lineId, 'date' => $cell['date'], 'shift' => $cell['shift']]],
            'line_id' => $lineId,
            'when' => $slot,
            'signature' => md5(json_encode([$cell['needed'], $cell['available'], $cell['absent_ids'], $cell['held_elsewhere'], $cell['order_ids']])),
            'sort' => $cell['start_minute'],
        ];
    }

    /**
     * d) work on a line nobody is assigned to.
     *
     * @param  list<array<string, mixed>>  $cells
     * @param  list<int>  $orderIds
     * @return array<string, mixed>
     */
    private function untrackedInsight(int $lineId, string $week, array $cells, array $orderIds): array
    {
        $line = $this->lineName($lineId);
        $affected = [];
        foreach ($orderIds as $id) {
            $order = $this->staffing->orders()->get($id);
            if ($order !== null) {
                $affected[] = $this->affectedEntry($order, null, __('not checked for people'));
            }
        }

        return [
            'key' => 'untracked:'.$lineId.':'.$week,
            'type' => 'untracked_line',
            'category' => 'plan',
            'severity' => 'info',
            'title' => __(':line is planned without people', ['line' => $line]),
            'meta' => __('No operators assigned to this line'),
            'impact' => trans_choice(':count order unchecked|:count orders unchecked', count($affected), ['count' => count($affected)]),
            'why' => __('OpenMES plans :line on machine capacity only. An absence there would not show up anywhere.', ['line' => $line]),
            'numbers' => null,
            'affected' => $affected,
            'actions' => $this->rank([
                ['kind' => 'link', 'href' => '/admin/lines/'.$lineId.'#operators', 'label' => __('Assign operators to :line', ['line' => $line]),
                    'effect' => __('Opens Admin → Lines → :line → Operators.', ['line' => $line]), 'score' => 2],
                ['kind' => 'acknowledge', 'label' => __('Acknowledge'), 'effect' => __('This line is planned on machines only, on purpose.'), 'score' => 0],
            ]),
            'cells' => $this->visibleCells(array_map(fn ($c) => ['line_id' => $lineId, 'date' => $c['date'], 'shift' => $c['shift']], $cells)),
            'line_id' => $lineId,
            'when' => null,
            'signature' => md5(json_encode($orderIds)),
            'sort' => PHP_INT_MAX,
        ];
    }

    /**
     * c) a skill planned work needs that only one person on the line holds.
     *
     * @param  list<array<string, mixed>>  $cells
     * @return list<array<string, mixed>>
     */
    private function singleSkillInsights(int $lineId, string $week, array $cells): array
    {
        $explains = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce : null;
        $holders = $this->skillHolders($lineId);
        if ($holders === null) {
            return [];
        }

        $bySkill = [];
        foreach ($cells as $cell) {
            foreach ($cell['order_ids'] as $orderId) {
                foreach ($this->staffing->bookingsOf($orderId) as $b) {
                    if ($b['line_id'] !== $lineId || $b['start'] >= $cell['end_minute'] || $b['end'] <= $cell['start_minute']) {
                        continue;
                    }
                    foreach ($b['skills'] as $skill) {
                        if (count($holders[$skill] ?? []) === 1) {
                            $bySkill[$skill]['orders'][$orderId] = true;
                            $bySkill[$skill]['cells'][$cell['date'].'|'.$cell['shift']] = ['line_id' => $lineId, 'date' => $cell['date'], 'shift' => $cell['shift']];
                            $bySkill[$skill]['sort'] = min($bySkill[$skill]['sort'] ?? PHP_INT_MAX, $cell['start_minute']);
                            $bySkill[$skill]['visible'] = ($bySkill[$skill]['visible'] ?? false) || in_array($cell['date'], $this->days, true);
                        }
                    }
                }
            }
        }

        $out = [];
        $line = $this->lineName($lineId);
        $skillNames = $bySkill === [] ? [] : $explains->skillNames(array_keys($bySkill));
        foreach ($bySkill as $skill => $data) {
            if (! $data['visible']) {
                continue;
            }
            $holder = $holders[$skill][0];
            $name = $this->keyName($holder);
            $skillName = $skillNames[$skill] ?? '#'.$skill;
            $affected = [];
            foreach (array_keys($data['orders']) as $id) {
                $order = $this->staffing->orders()->get($id);
                if ($order !== null) {
                    $affected[] = $this->affectedEntry($order, null);
                }
            }
            $actions = [];
            foreach ($this->qualifiedElsewhere($lineId, [$skill]) as $candidate) {
                $actions[] = [
                    'kind' => 'substitute', 'user_id' => $candidate['user_id'], 'line_id' => $lineId,
                    'label' => __('Also assign :name to :line', ['name' => $candidate['name'], 'line' => $line]),
                    'effect' => __('Holds :skill too, so :line no longer depends on one person.', ['skill' => $skillName, 'line' => $line]),
                    'score' => 2,
                ];
            }
            $actions[] = ['kind' => 'acknowledge', 'label' => __('Acknowledge'), 'effect' => __('Keep as is. It reopens if the plan changes.'), 'score' => 1];

            $out[] = [
                'key' => 'skill:'.$lineId.':'.$skill.':'.$week,
                'type' => 'single_skill',
                'category' => 'skills',
                'severity' => 'warn',
                'title' => __(':skill depends on one person', ['skill' => $skillName]),
                'meta' => __(':name is the only qualified operator on :line', ['name' => $name, 'line' => $line]),
                'impact' => trans_choice(':count order|:count orders', count($affected), ['count' => count($affected)]),
                'why' => __('Planned work on :line needs :skill. Only :name holds it, so one absence would stop that work.', ['line' => $line, 'skill' => $skillName, 'name' => $name]),
                'numbers' => ['qualified' => 1],
                'affected' => $affected,
                'actions' => $this->rank($actions),
                'cells' => $this->visibleCells(array_values($data['cells'])),
                'line_id' => $lineId,
                'when' => null,
                'signature' => md5(json_encode([$holder, array_keys($data['orders'])])),
                'sort' => $data['sort'],
            ];
        }

        return $out;
    }

    /**
     * e) orders that lost people since they were planned.
     *
     * @param  list<int>  $orderIds
     * @return list<array<string, mixed>>
     */
    private function changedInsights(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $snapshots = WorkOrderStaffingSnapshot::whereIn('work_order_id', $orderIds)->get()->keyBy('work_order_id');
        $out = [];
        foreach ($orderIds as $id) {
            $order = $this->staffing->orders()->get($id);
            $losses = $order ? $this->snapshots->losses($order, $snapshots->get($id)) : null;
            if ($losses === null) {
                continue;
            }

            $changes = [];
            foreach ($losses['absent'] as $date => $ids) {
                foreach ($ids as $worker) {
                    $changes[] = __(':name absent on :date', ['name' => $this->workerName($worker), 'date' => $this->dateLabel($date)]);
                }
            }
            foreach ($losses['unassigned'] as $line => $keys) {
                foreach ($keys as $key) {
                    $changes[] = __(':name no longer on :line', ['name' => $this->keyName($key), 'line' => $this->lineName($line)]);
                }
            }
            $cells = [];
            foreach ($this->staffing->bookingsOf($id) as $b) {
                foreach ($b['cells'] as $c) {
                    [$l, $date, $col] = explode('|', $c);
                    if (in_array($date, $this->days, true)) {
                        $cells[] = ['line_id' => (int) $l, 'date' => $date, 'shift' => (int) $col];
                    }
                }
            }

            $out[] = [
                'key' => 'changed:'.$id,
                'type' => 'staffing_changed',
                'category' => 'people',
                'severity' => 'warn',
                'title' => __('Staffing changed for :order', ['order' => $order->order_no]),
                'meta' => implode(' · ', array_slice($changes, 0, 2)).(count($changes) > 2 ? ' · …' : ''),
                'impact' => __('planned :date', ['date' => $losses['captured_at']?->format('d.m H:i') ?? '—']),
                'why' => __('Since :order was planned, its staffing changed: :changes. Check that it still has the people it needs.', [
                    'order' => $order->order_no, 'changes' => implode('; ', $changes),
                ]),
                'numbers' => null,
                'affected' => [$this->affectedEntry($order, null)],
                'actions' => $this->rank([
                    ['kind' => 'link', 'href' => '/admin/schedule/auto', 'label' => __('Open in Automatic scheduling'), 'effect' => __('Plan it again with today\'s staffing.'), 'score' => 1],
                    ['kind' => 'acknowledge', 'label' => __('Acknowledge'), 'effect' => __('It still has enough people. Reopens if staffing changes again.'), 'score' => 2],
                ]),
                'cells' => $cells,
                'line_id' => $order->line_id,
                'when' => null,
                'signature' => $losses['fingerprint'],
                'sort' => PHP_INT_MAX - 1,
            ];
        }

        return $out;
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    /**
     * Mark an open insight as dealt with.
     *
     * @throws \DomainException when the insight is not on the board
     * @throws InsightConflict when someone already handled it
     */
    public function acknowledge(string $key, User $user): PlanningInsightAcknowledgement
    {
        $insight = $this->openInsight($key);

        return $this->handle($insight, $user, fn () => [
            PlanningInsightAcknowledgement::ACTION_ACKNOWLEDGE,
            __('Acknowledged by :name.', ['name' => $user->name]),
            null,
        ]);
    }

    /**
     * Move an order of a short-shift insight to the first slot with enough
     * people, through the planner's own move. The move and the
     * acknowledgement commit together or not at all, and the acknowledgement
     * keeps the id of the change-log row this very move wrote, so an undo
     * reverts exactly that.
     *
     * @return array{conflict: bool, message?: string, acknowledgement?: PlanningInsightAcknowledgement}
     *
     * @throws \DomainException when the insight or the move is not on offer, or the move would change nothing
     * @throws InsightConflict when someone already handled the insight
     */
    public function move(string $key, int $orderId, User $user, bool $force = false): array
    {
        $insight = $this->openInsight($key);
        $action = collect($insight['actions'])->first(fn ($a) => $a['kind'] === 'move' && $a['work_order_id'] === $orderId);
        $order = $this->staffing->orders()->get($orderId);
        $cell = $insight['cells'][0] ?? null;
        $target = $order && $cell ? $this->moveTarget($order, $this->staffing->cell($cell['line_id'], $cell['date'], $cell['shift'])) : null;
        if ($action === null || $target === null) {
            throw new \DomainException(__('That move is no longer possible. Refresh the insights.'));
        }

        try {
            $ack = $this->handle($insight, $user, function () use ($order, $target, $force) {
                $result = $this->planner->updateOrder($order, $target['placement'], $force);
                if ($result['conflict']) {
                    throw new MoveConflict($result['message']);
                }
                if (($result['change_log_id'] ?? null) === null) {
                    throw new \DomainException(__('Nothing to move: the order is already there.'));
                }

                return [
                    PlanningInsightAcknowledgement::ACTION_MOVE,
                    __('Moved :order to :slot.', ['order' => $order->order_no, 'slot' => $target['label']]),
                    ['work_order_id' => $order->id, 'change_log_id' => (int) $result['change_log_id']],
                ];
            });
        } catch (MoveConflict $e) {
            return ['conflict' => true, 'message' => $e->getMessage()];
        }

        return ['conflict' => false, 'acknowledgement' => $ack];
    }

    /**
     * Assign a suggested operator to the insight's line, through the existing
     * line-operator assignment.
     *
     * @throws \DomainException when the insight or the person is not on offer
     * @throws InsightConflict when someone already handled the insight
     */
    public function substitute(string $key, int $userId, User $actor): PlanningInsightAcknowledgement
    {
        $insight = $this->openInsight($key);
        $action = collect($insight['actions'])->first(fn ($a) => $a['kind'] === 'substitute' && $a['user_id'] === $userId);
        $operator = User::find($userId);
        $line = Line::find($insight['line_id']);
        if ($action === null || $operator === null || $line === null) {
            throw new \DomainException(__('That person is no longer a suggested substitute. Refresh the insights.'));
        }

        return $this->handle($insight, $actor, function () use ($operator, $line, $actor) {
            $this->assignment->assign($line, $operator);

            return [
                PlanningInsightAcknowledgement::ACTION_SUBSTITUTE,
                __(':name assigned to :line by :by.', ['name' => $operator->name, 'line' => $line->name, 'by' => $actor->name]),
                ['line_id' => $line->id, 'user_id' => $operator->id],
            ];
        });
    }

    /**
     * Undo what an acknowledgement recorded — revert the move, take the
     * substitute off the line — and reopen the insight.
     *
     * @throws InsightConflict when the order was edited since the move, or the acknowledgement is gone
     */
    public function undo(PlanningInsightAcknowledgement $ack): void
    {
        DB::transaction(function () use ($ack) {
            $ack = PlanningInsightAcknowledgement::whereKey($ack->id)->lockForUpdate()->first();
            if ($ack === null) {
                throw new InsightConflict(__('This was already undone. Refresh the insights.'));
            }
            $payload = $ack->payload ?? [];
            if ($ack->action === PlanningInsightAcknowledgement::ACTION_MOVE) {
                $this->undoMove($payload);
            }
            if ($ack->action === PlanningInsightAcknowledgement::ACTION_SUBSTITUTE && ! empty($payload['line_id']) && ! empty($payload['user_id'])) {
                $line = Line::find($payload['line_id']);
                $user = User::find($payload['user_id']);
                if ($line !== null && $user !== null) {
                    $this->assignment->unassign($line, $user);
                }
            }
            $ack->delete();
        });
        $this->invalidate();
    }

    /**
     * Revert exactly the change the move wrote — only while it is the order's
     * last edit and the order still sits where the move put it.
     */
    private function undoMove(array $payload): void
    {
        $change = ! empty($payload['change_log_id']) ? ScheduleChangeLog::find($payload['change_log_id']) : null;
        $order = $change !== null ? WorkOrder::whereKey($change->work_order_id)->lockForUpdate()->first() : null;
        if ($change === null || $order === null || (int) $change->work_order_id !== (int) ($payload['work_order_id'] ?? 0)) {
            throw new InsightConflict(__('The move cannot be found any more. Undo it from the planner\'s Changes tab.'));
        }
        $editedSince = $change->undone_at !== null
            || ScheduleChangeLog::where('work_order_id', $order->id)->where('id', '>', $change->id)->exists()
            || $this->planner->placementSnapshot($order->load('extraPlacements')) != $change->after;
        if ($editedSince) {
            throw new InsightConflict(__(':order was changed again since. Undo it from the planner\'s Changes tab.', ['order' => $order->order_no]));
        }
        $this->planner->undoChange($change);
    }

    /**
     * The insight as it stands now, if it is on the board and not handled.
     *
     * @return array<string, mixed>
     *
     * @throws \DomainException when the insight is not on the board
     * @throws InsightConflict when someone already handled it
     */
    private function openInsight(string $key): array
    {
        $insight = $this->find($key);
        if ($insight === null) {
            throw new \DomainException(__('This insight is no longer open. Refresh the insights.'));
        }
        $this->assertNotHandled($insight);

        return $insight;
    }

    /** @throws InsightConflict */
    private function assertNotHandled(array $insight, bool $lock = false): void
    {
        $query = PlanningInsightAcknowledgement::with('acknowledgedBy:id,name')->where('insight_key', $insight['key']);
        $ack = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($ack !== null && $ack->signature === $insight['signature']) {
            throw new InsightConflict($this->handledBy($ack));
        }
    }

    private function handledBy(?PlanningInsightAcknowledgement $ack): string
    {
        $name = $ack?->acknowledgedBy?->name;

        return $name !== null
            ? __('Already handled by :name.', ['name' => $name])
            : __('Already handled by another planner. Refresh the insights.');
    }

    /**
     * Act on an insight and record it, atomically and one planner at a time:
     * the insight key is locked, its state re-read under the lock, then the
     * action runs and the acknowledgement is written in the same transaction.
     * Should another planner's row still slip in (the partial unique index
     * says so), everything — the move included — rolls back and the caller
     * gets a 409.
     *
     * @param  callable(): array{0: string, 1: string, 2: ?array<string, mixed>}  $do  the action; returns [action, summary, payload]
     *
     * @throws InsightConflict
     */
    private function handle(array $insight, User $user, callable $do): PlanningInsightAcknowledgement
    {
        try {
            $ack = DB::transaction(function () use ($insight, $user, $do) {
                $this->lockKey($insight['key']);
                $this->assertNotHandled($insight, lock: true);
                [$action, $summary, $payload] = $do();

                return $this->record($insight, $action, $user, $summary, $payload);
            });
        } catch (UniqueConstraintViolationException) {
            throw new InsightConflict($this->handledBy(
                PlanningInsightAcknowledgement::with('acknowledgedBy:id,name')->where('insight_key', $insight['key'])->first()
            ));
        }
        $this->invalidate();

        return $ack;
    }

    /**
     * Serialise actions on one insight of one plant (PostgreSQL; elsewhere
     * the unique index alone decides). The key is a bound parameter.
     */
    private function lockKey(string $key): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $tenant = app(\App\Support\TenantContext::class)->id() ?? 0;
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['planning-insight:'.$tenant.':'.$key]);
    }

    private function record(array $insight, string $action, User $user, string $summary, ?array $payload): PlanningInsightAcknowledgement
    {
        // A reopened insight's earlier acknowledgement makes way for this one.
        PlanningInsightAcknowledgement::where('insight_key', $insight['key'])->get()->each->delete();

        return PlanningInsightAcknowledgement::create([
            'insight_key' => $insight['key'],
            'insight_type' => $insight['type'],
            'action' => $action,
            'title' => $insight['title'],
            'summary' => $summary,
            'signature' => $insight['signature'],
            'payload' => $payload,
            'acknowledged_by_id' => $user->id,
        ]);
    }

    // ── Moves and substitutes ───────────────────────────────────────────────

    /**
     * The first slot from the cell on where the order's primary placement on
     * this line would have enough people, keeping its length in real shifts —
     * the shifts the line runs, not board columns it has no shift in or a
     * weekend without shifts — so a moved order is exactly as long as before;
     * with the planner body that moves it there, like a drop on the weekly
     * board. Null when the order is not a coarse primary placement on
     * the line, or nothing within the lookahead fits.
     *
     * @return array{label: string, spare: int, late: bool, end_minute: int, placement: array<string, mixed>}|null
     */
    private function moveTarget(WorkOrder $order, ?array $cell): ?array
    {
        if ($cell === null) {
            return null;
        }
        $lineId = $cell['line_id'];
        $own = [];
        $crew = 0;
        foreach ($this->staffing->bookingsOf($order->id) as $b) {
            if ($b['primary'] && $b['line_id'] === $lineId && $b['cells'] !== []) {
                $own[$b['cells'][0]] = true;
                $crew = $b['operators'];
            }
        }
        if ($own === [] || $order->status === WorkOrder::STATUS_IN_PROGRESS) {
            return null;
        }
        $span = 0;
        foreach (array_keys($own) as $ownCell) {
            [, $date, $col] = explode('|', $ownCell);
            $span += $this->staffing->isShift($lineId, $date, (int) $col) ? 1 : 0;
        }
        // Placed only where the line has no shift: it takes one shift.
        $span = max(1, $span);
        $columns = count($this->staffing->columns());

        // Shift cells of the line after the cell, in board order.
        $candidates = [];
        $day = Carbon::parse($cell['date']);
        $limit = $this->to->copy()->addDays(PlannerStaffing::LOOKAHEAD_DAYS);
        for (; $day->lte($limit); $day->addDay()) {
            $date = $day->toDateString();
            for ($col = 1; $col <= $columns; $col++) {
                if (($date === $cell['date'] && $col <= $cell['shift']) || ! $this->staffing->isShift($lineId, $date, $col)) {
                    continue;
                }
                $candidates[] = [$date, $col];
            }
        }

        for ($i = 0; $i + $span <= count($candidates); $i++) {
            $spare = PHP_INT_MAX;
            for ($j = $i; $j < $i + $span; $j++) {
                [$date, $col] = $candidates[$j];
                $c = $this->staffing->cell($lineId, $date, $col);
                if ($c === null || ! $c['tracked']) {
                    $spare = -1;
                    break;
                }
                $mine = isset($own[$lineId.'|'.$date.'|'.$col]) ? $crew : 0;
                $spare = min($spare, $c['available'] - $c['needed'] + $mine - $crew);
            }
            if ($spare < 0) {
                continue;
            }
            [$startDate, $startCol] = $candidates[$i];
            [$endDate, $endCol] = $candidates[$i + $span - 1];
            $window = $this->staffing->window($startDate, $startCol);
            $end = $this->staffing->window($endDate, $endCol)[1];
            $start = ResourceCalendar::toCarbon($window[0]);

            return [
                'label' => $this->slotLabel(['date' => $startDate, 'shift' => $startCol]),
                'spare' => $spare,
                'late' => $this->isLate($order, $end),
                'end_minute' => $end,
                'placement' => [
                    'line_id' => $lineId,
                    'planned_start_at' => $start->format('Y-m-d H:i:s'),
                    'shift_number' => $startCol,
                    'week_number' => null,
                    'end_date' => $endDate,
                    'end_shift_number' => $endCol,
                    'planned_end_at' => Carbon::parse($endDate)->addDay()->format('Y-m-d 00:00:00'),
                ],
            ];
        }

        return null;
    }

    /**
     * Operators who could cover the cell: Operator accounts of this plant not
     * assigned to the line, not away in the shift, not needed by work on
     * their own lines then and — when the provider records skills — holding
     * what the cell's work requires. Best first.
     *
     * @return list<array<string, mixed>>
     */
    private function substitutes(array $cell, int $missing): array
    {
        $lineId = $cell['line_id'];
        $skills = [];
        foreach ($cell['order_ids'] as $id) {
            foreach ($this->staffing->bookingsOf($id) as $b) {
                if ($b['line_id'] === $lineId && $b['start'] < $cell['end_minute'] && $b['end'] > $cell['start_minute']) {
                    $skills = [...$skills, ...$b['skills']];
                }
            }
        }
        $skills = array_values(array_unique($skills));
        $line = $this->lineName($lineId);
        $out = [];
        foreach ($this->freeOperators($lineId, $cell['start_minute'], $cell['end_minute'], $skills) as $c) {
            $effect = [$c['other_line'] === null
                ? __('Free in this shift, no other line.')
                : ($c['spare_after'] === 0
                    ? __('Free in this shift. :other drops to exactly enough.', ['other' => $c['other_line']])
                    : __('Free in this shift. :other keeps :spare spare.', ['other' => $c['other_line'], 'spare' => $c['spare_after']]))];
            if ($skills !== [] && $this->skillsTracked()) {
                $effect[] = __('Holds the skills this work needs.');
            }
            $effect[] = $missing <= 1 ? __('Covers the shortfall.') : __('Covers 1 of :missing missing.', ['missing' => $missing]);
            $out[] = [
                'kind' => 'substitute',
                'user_id' => $c['user_id'],
                'line_id' => $lineId,
                'label' => $c['other_line'] === null
                    ? __('Bring in :name', ['name' => $c['name']])
                    : __('Bring in :name from :other', ['name' => $c['name'], 'other' => $c['other_line']]),
                'effect' => implode(' ', $effect),
                'confirm' => __('Assign :name to :line? They stay on their other lines too.', ['name' => $c['name'], 'line' => $line]),
                'score' => ($missing <= 1 ? 4 : 2) + ($c['other_line'] === null ? 0.5 : 0) + min(0.4, $c['spare_after'] / 10),
            ];
        }

        return array_slice($out, 0, 3);
    }

    /**
     * Operators qualified in the skills who could join the line (for a
     * single-holder skill): same rules as substitutes, any time.
     *
     * @param  list<int>  $skills
     * @return list<array{user_id: int, name: string}>
     */
    private function qualifiedElsewhere(int $lineId, array $skills): array
    {
        $start = ResourceCalendar::toMinutes($this->from);
        $end = ResourceCalendar::toMinutes($this->to->copy()->addDay());

        return array_map(fn ($c) => ['user_id' => $c['user_id'], 'name' => $c['name']],
            array_slice($this->freeOperators($lineId, $start, $end, $skills, ignoreWork: true), 0, 2));
    }

    /**
     * @param  list<int>  $skills
     * @return list<array{user_id: int, name: string, other_line: ?string, spare_after: int}>
     */
    private function freeOperators(int $lineId, int $start, int $end, array $skills, bool $ignoreWork = false): array
    {
        $from = ResourceCalendar::toCarbon($start);
        $to = ResourceCalendar::toCarbon($end);
        $absent = [];
        foreach (ShiftOccurrences::attributedDates($this->staffing->shifts(), $lineId, $start, $end) as $date) {
            $absent += $this->absentOn[$date] ??= array_fill_keys(
                array_map('intval', $this->people->absentWorkerIds(Carbon::parse($date)->startOfDay())), true);
        }
        $checkSkills = $skills !== [] && $this->skillsTracked();

        $out = [];
        $this->operatorAccounts ??= User::role('Operator')->with('lines:id,name,is_active')->orderBy('name')->get();
        foreach ($this->operatorAccounts as $user) {
            $lines = $user->lines->where('is_active', true);
            if ($lines->contains('id', $lineId) || ($user->worker_id && isset($absent[(int) $user->worker_id]))) {
                continue;
            }
            $key = $user->worker_id ? 'w:'.$user->worker_id : 'u:'.$user->id;

            if ($checkSkills) {
                $held = [];
                foreach ($lines as $l) {
                    foreach ($this->skillHolders((int) $l->id) ?? [] as $skill => $keys) {
                        if (in_array($key, $keys, true)) {
                            $held[(int) $skill] = true;
                        }
                    }
                }
                if (array_diff($skills, array_keys($held)) !== []) {
                    continue;
                }
            }

            $spareAfter = PHP_INT_MAX;
            $busiest = null;
            foreach ($lines as $l) {
                if ($ignoreWork) {
                    $busiest ??= (string) $l->name;

                    continue;
                }
                $c = $this->staffing->counter()->count((int) $l->id, $start, $end);
                if (! $c['tracked'] || $c['needed'] === 0) {
                    $busiest ??= (string) $l->name;
                    $spareAfter = min($spareAfter, max(0, ($c['available'] ?? 1) - 1));

                    continue;
                }
                $left = $c['available'] - $c['needed'] - 1;
                if ($left < 0) {
                    continue 2; // that line needs them
                }
                if ($left < $spareAfter) {
                    $spareAfter = $left;
                    $busiest = (string) $l->name;
                }
            }

            $out[] = [
                'user_id' => $user->id,
                'name' => (string) $user->name,
                'other_line' => $busiest,
                'spare_after' => $spareAfter === PHP_INT_MAX ? 0 : $spareAfter,
            ];
        }
        usort($out, fn ($a, $b) => [$a['other_line'] === null ? 1 : 0, -$a['spare_after'], $a['name']] <=> [$b['other_line'] === null ? 1 : 0, -$b['spare_after'], $b['name']]);

        return $out;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Rank actions by their effect and mark the best one.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    private function rank(array $actions): array
    {
        usort($actions, fn ($a, $b) => $b['score'] <=> $a['score']);
        foreach ($actions as $i => &$a) {
            $a['best'] = $i === 0 && $a['score'] > 0 && $a['kind'] !== 'acknowledge';
            unset($a['score']);
        }

        return $actions;
    }

    /**
     * @param  array{label: string, late: bool, end_minute: int}|null  $move
     * @return array<string, mixed>
     */
    private function affectedEntry(WorkOrder $order, ?array $move, ?string $note = null): array
    {
        $end = $this->endOf($order);
        $lateNow = $end !== null && $this->isLate($order, $end);
        if ($note === null) {
            $note = match (true) {
                $move !== null => $move['late']
                    ? __('if it waits for :slot, it is late', ['slot' => $move['label']])
                    : __('if it waits for :slot, still on time', ['slot' => $move['label']]),
                $lateNow => __('finishes after its due date'),
                default => __('on time'),
            };
        }

        return [
            'work_order_id' => $order->id,
            'order_no' => $order->order_no,
            'product' => $order->productType?->name,
            'due_date' => $order->due_date?->toDateString(),
            'late' => $lateNow || ($move['late'] ?? false),
            'note' => $note,
        ];
    }

    private function endOf(WorkOrder $order): ?int
    {
        $end = null;
        foreach ($this->staffing->bookingsOf($order->id) as $b) {
            $end = max($end ?? $b['end'], $b['end']);
        }

        return $end;
    }

    private function isLate(WorkOrder $order, int $endMinute): bool
    {
        return $order->due_date !== null
            && $endMinute > ResourceCalendar::toMinutes(Carbon::parse($order->due_date->toDateString())->addDay());
    }

    /** @param  list<int>  $orderIds */
    private function hasCommitted(array $orderIds): bool
    {
        foreach ($orderIds as $id) {
            foreach ($this->staffing->bookingsOf($id) as $b) {
                if ($b['cells'] === [] && $b['primary']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function skillsTracked(): bool
    {
        if ($this->skillsTracked !== null) {
            return $this->skillsTracked;
        }
        foreach ($this->staffing->lines()->keys() as $id) {
            if ($this->skillHolders((int) $id) !== null) {
                return $this->skillsTracked = true;
            }
        }

        return $this->skillsTracked = false;
    }

    /**
     * Who holds which skill on a line, read once per load; null when the
     * provider records no skills there.
     *
     * @return array<int, list<string>>|null
     */
    private function skillHolders(int $lineId): ?array
    {
        if (! $this->workforce instanceof ExplainsWorkforceCapacity) {
            return null;
        }
        if (! array_key_exists($lineId, $this->holders)) {
            $this->holders[$lineId] = $this->workforce->skillHolders($lineId);
        }

        return $this->holders[$lineId];
    }

    /** Monday of the ISO week a date falls in — the period of week-long insight keys. */
    public static function weekOf(string $date): string
    {
        return Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /**
     * @param  list<array{line_id: int, date: string, shift: int}>  $cells
     * @return list<array{line_id: int, date: string, shift: int}>
     */
    private function visibleCells(array $cells): array
    {
        return array_values(array_filter($cells, fn ($c) => in_array($c['date'], $this->days, true)));
    }

    private function categoryOf(string $type): string
    {
        return match ($type) {
            'single_skill' => 'skills',
            'untracked_line' => 'plan',
            default => 'people',
        };
    }

    private function slotLabel(array $cell): string
    {
        $name = $this->staffing->columns()[$cell['shift'] - 1]['name'] ?? '';

        return trim($this->dateLabel($cell['date']).' '.$name);
    }

    private function dateLabel(string $date): string
    {
        return Carbon::parse($date)->locale(app()->getLocale())->isoFormat('ddd DD.MM');
    }

    private function lineName(int $lineId): string
    {
        return (string) ($this->staffing->lines()->get($lineId)?->name ?? '#'.$lineId);
    }

    private function workerName(int $id): string
    {
        if (! array_key_exists($id, $this->workerNames)) {
            $names = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce->workerNames([$id]) : [];
            $this->workerNames[$id] = $names[$id] ?? null;
        }

        return $this->workerNames[$id] ?? '#'.$id;
    }

    private function keyName(string $key): string
    {
        if (! array_key_exists($key, $this->keyNames)) {
            $names = $this->workforce instanceof ExplainsWorkforceCapacity ? $this->workforce->operatorNames([$key]) : [];
            $this->keyNames[$key] = $names[$key] ?? $key;
        }

        return $this->keyNames[$key];
    }
}
