<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Shift;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $request->filled('week')
            ? \Carbon\Carbon::parse($request->week)->startOfWeek()
            : now()->startOfWeek();

        $weekEnd    = $weekStart->copy()->endOfWeek();
        $lineId     = $request->input('line_id');
        $lines      = Line::where('is_active', true)->orderBy('name')->get();
        $currentShift = Shift::current($lineId ?: null);

        // Work orders due this week (or no due date but active)
        $query = WorkOrder::with(['line', 'productType'])
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('due_date', [$weekStart, $weekEnd])
                  ->orWhereNull('due_date');
            })
            ->orderByRaw("CASE status
                WHEN 'BLOCKED'     THEN 1
                WHEN 'IN_PROGRESS' THEN 2
                WHEN 'ACCEPTED'    THEN 3
                WHEN 'PENDING'     THEN 4
                ELSE 5 END")
            ->orderBy('due_date')
            ->orderBy('priority', 'desc');

        if ($lineId) {
            $query->where('line_id', $lineId);
        }

        $workOrders = $query->get();

        // Group by line for the overview
        $byLine = $workOrders->groupBy('line_id');

        // Days of the week for the schedule header
        $days = collect(range(0, 6))->map(fn($i) => $weekStart->copy()->addDays($i));

        return view('admin.schedule.index', compact(
            'workOrders',
            'byLine',
            'lines',
            'days',
            'weekStart',
            'weekEnd',
            'lineId',
            'currentShift',
        ));
    }
}
