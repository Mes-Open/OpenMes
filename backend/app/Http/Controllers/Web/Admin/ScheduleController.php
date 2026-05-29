<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Shift;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        return Inertia::render('admin/schedule/Index', [
            'workOrders' => $workOrders->map(fn ($wo) => [
                'id'          => $wo->id,
                'order_no'    => $wo->order_no,
                'product_name'=> $wo->productType?->name,
                'line_id'     => $wo->line_id,
                'due_date'    => $wo->due_date?->format('Y-m-d'),
                'planned_qty' => $wo->planned_qty,
                'status'      => $wo->status,
                'priority'    => $wo->priority,
            ])->values(),
            'byLine'       => $byLine->keys()->all(),
            'lines'        => $lines->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'code' => $l->code])->values(),
            'days'         => $days->map(fn ($d) => $d->format('Y-m-d'))->values(),
            'weekStart'    => $weekStart->toIso8601String(),
            'weekEnd'      => $weekEnd->toIso8601String(),
            'lineId'       => $lineId,
            'currentShift' => $currentShift ? [
                'name'       => $currentShift->name,
                'start_time' => $currentShift->start_time,
                'end_time'   => $currentShift->end_time,
            ] : null,
        ]);
    }
}
