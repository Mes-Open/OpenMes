<?php

namespace App\Services\Schedule\Insights;

use App\Extension\Contracts\WorkforceProvider;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Models\WorkOrderStaffingSnapshot;
use App\Services\Schedule\Finite\AssignedStaff;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The staffing an order was planned against, and what has been lost since.
 *
 * The same idea as an automatic-scheduling draft's staffing fingerprint, per
 * order: when an order is placed (a planner move, a resize, an applied draft)
 * this records who staffs its lines and who of them is away on its days. Later
 * the planner compares that with today's staffing and lists the losses — an
 * operator no longer assigned, a new absence — so work planned in good faith
 * does not silently lose its people. Gains are not reported: they cannot hurt.
 *
 * Reads the people through {@see WorkforceProvider} and {@see AssignedStaff},
 * the sources core records; nothing here depends on how absences are kept.
 */
class StaffingSnapshots
{
    public function __construct(private readonly WorkforceProvider $people) {}

    /**
     * Record (or replace) the order's snapshot; drop it when the order is no
     * longer placed. Never throws: a placement must not fail over this.
     */
    public function capture(WorkOrder $order): void
    {
        try {
            $state = $this->stateOf($order);
            if ($state === null) {
                WorkOrderStaffingSnapshot::where('work_order_id', $order->id)->delete();

                return;
            }
            WorkOrderStaffingSnapshot::updateOrCreate(
                ['work_order_id' => $order->id],
                [
                    'fingerprint' => $state['fingerprint'],
                    'roster' => $state['roster'],
                    'absences' => $state['absences'],
                    'window_start' => $state['start'],
                    'window_end' => $state['end'],
                    'tenant_id' => $order->tenant_id,
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * What the order lost since its snapshot: per line, people no longer
     * assigned; per date, people newly away. Null when nothing was lost, or
     * the order was never snapshotted.
     *
     * @return array{fingerprint: string, captured_at: CarbonInterface, unassigned: array<int, list<string>>, absent: array<string, list<int>>}|null
     */
    public function losses(WorkOrder $order, ?WorkOrderStaffingSnapshot $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }
        $now = $this->stateOf($order, $snapshot->window_start, $snapshot->window_end, array_map('intval', array_keys($snapshot->roster ?? [])));
        if ($now === null || $now['fingerprint'] === $snapshot->fingerprint) {
            return null;
        }

        $unassigned = [];
        foreach ($snapshot->roster ?? [] as $line => $keys) {
            $gone = array_values(array_diff($keys, $now['roster'][$line] ?? []));
            if ($gone !== []) {
                $unassigned[(int) $line] = $gone;
            }
        }
        $absent = [];
        foreach ($now['absences'] as $date => $ids) {
            $new = array_values(array_diff($ids, $snapshot->absences[$date] ?? []));
            if ($new !== []) {
                $absent[$date] = $new;
            }
        }
        if ($unassigned === [] && $absent === []) {
            return null;
        }

        return ['fingerprint' => $now['fingerprint'], 'captured_at' => $snapshot->updated_at, 'unassigned' => $unassigned, 'absent' => $absent];
    }

    /**
     * The order's lines and window, the people staffing those lines, and the
     * absences among them on the window's dates.
     *
     * @param  list<int>|null  $lines  compare on these lines (the snapshot's), not the order's current ones
     * @return array{fingerprint: string, roster: array<int, list<string>>, absences: array<string, list<int>>, start: CarbonInterface, end: CarbonInterface}|null
     */
    private function stateOf(WorkOrder $order, ?CarbonInterface $start = null, ?CarbonInterface $end = null, ?array $lines = null): ?array
    {
        if ($start === null || $end === null) {
            [$start, $end] = $this->windowOf($order) ?? [null, null];
        }
        $lines ??= $this->linesOf($order);
        if ($start === null || $lines === []) {
            return null;
        }

        $staff = AssignedStaff::byLine($this->people);
        $roster = [];
        $workers = [];
        foreach ($lines as $line) {
            $keys = $staff[$line] ?? [];
            sort($keys);
            $roster[$line] = $keys;
            foreach ($keys as $key) {
                if (str_starts_with($key, 'w:')) {
                    $workers[(int) substr($key, 2)] = true;
                }
            }
        }
        ksort($roster);

        // A night shift starting the evening before reaches into the window.
        $first = Carbon::parse($start)->startOfDay();
        if ($this->anyShiftCrossesMidnight()) {
            $first->subDay();
        }
        $absences = [];
        for ($day = $first->copy(); $day->lessThan($end); $day->addDay()) {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', $this->people->absentWorkerIds($day->copy())),
                fn (int $id) => isset($workers[$id]),
            )));
            sort($ids);
            if ($ids !== []) {
                $absences[$day->toDateString()] = $ids;
            }
        }

        return [
            'fingerprint' => md5(json_encode([$roster, $absences])),
            'roster' => $roster,
            'absences' => $absences,
            'start' => Carbon::parse($start),
            'end' => Carbon::parse($end),
        ];
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface}|null */
    private function windowOf(WorkOrder $order): ?array
    {
        if ($order->line_id === null) {
            return null;
        }
        $dates = [];
        $first = $order->planned_start_at ?? $order->due_date;
        if ($first === null) {
            return null;
        }
        $start = Carbon::parse($first);
        $end = $order->planned_end_at !== null && $order->planned_end_at->greaterThan($start)
            ? Carbon::parse($order->planned_end_at)
            : Carbon::parse($order->end_date ?? $start)->startOfDay()->addDay();
        if ($order->end_date !== null) {
            $dates[] = Carbon::parse($order->end_date)->startOfDay()->addDay();
        }
        foreach ($order->extraPlacements()->get() as $p) {
            if ($p->due_date !== null) {
                $start = Carbon::parse($p->due_date)->startOfDay()->min($start);
                $dates[] = Carbon::parse($p->end_date ?? $p->due_date)->startOfDay()->addDay();
            }
        }
        foreach ($dates as $date) {
            $end = $date->greaterThan($end) ? $date : $end;
        }

        return [$start, $end];
    }

    /** @return list<int> */
    private function linesOf(WorkOrder $order): array
    {
        if ($order->line_id === null) {
            return [];
        }
        $lines = [(int) $order->line_id => true];
        foreach ($order->extraPlacements()->pluck('line_id') as $id) {
            $lines[(int) $id] = true;
        }
        foreach (\App\Models\ScheduledOperation::committed()->where('work_order_id', $order->id)->whereNotNull('line_id')->pluck('line_id') as $id) {
            $lines[(int) $id] = true;
        }
        ksort($lines);

        return array_keys($lines);
    }

    private function anyShiftCrossesMidnight(): bool
    {
        return Shift::where('is_active', true)->get(['start_time', 'end_time'])
            ->contains(fn (Shift $s) => strtotime((string) $s->end_time) < strtotime((string) $s->start_time));
    }
}
