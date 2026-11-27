<?php

namespace App\Services\Production;

use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use App\Models\Workstation;
use App\Support\ShiftWindow;

/**
 * The figures in the operator shell's shift strip: the shift running now on
 * the line, and the line's orders counted the way the operator reads them
 * (running, blocked, due today) — one query per figure, whatever the page.
 */
class OperatorShiftSummary
{
    /**
     * @return array{
     *     shift: array{name: ?string, code: ?string, start: string, end: string},
     *     in_progress: int, blocked: int, due_today: int, workstations: int,
     *     made_this_shift: ?float
     * }
     */
    public function forLine(int $lineId): array
    {
        $window = ShiftWindow::current($lineId);

        $counts = WorkOrder::availableForProduction()
            ->where('line_id', $lineId)
            ->whereIn('status', [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_BLOCKED])
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $dueToday = WorkOrder::availableForProduction()
            ->where('line_id', $lineId)
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->whereDate('due_date', now()->toDateString())
            ->count();

        // Counted from the per-shift quantity entries, the only production the
        // line books against a shift. Null without a configured shift: the
        // fallback 12h window has no entries of its own to add up.
        $made = null;
        if ($window->shift) {
            $made = (float) WorkOrderShiftEntry::where('shift_id', $window->shift->id)
                ->whereDate('production_date', '>=', $window->start->toDateString())
                ->whereDate('production_date', '<=', $window->end->toDateString())
                ->whereHas('workOrder', fn ($q) => $q->where('line_id', $lineId))
                ->sum('quantity');
        }

        return [
            'shift' => [
                'name' => $window->shift?->name,
                'code' => $window->shift?->code,
                'start' => $window->start->toIso8601String(),
                'end' => $window->end->toIso8601String(),
            ],
            'in_progress' => (int) ($counts[WorkOrder::STATUS_IN_PROGRESS] ?? 0),
            'blocked' => (int) ($counts[WorkOrder::STATUS_BLOCKED] ?? 0),
            'due_today' => $dueToday,
            'workstations' => Workstation::where('line_id', $lineId)->where('is_active', true)->count(),
            'made_this_shift' => $made,
        ];
    }
}
