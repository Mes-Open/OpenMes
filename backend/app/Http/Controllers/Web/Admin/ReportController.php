<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $period  = in_array($request->input('period'), ['weekly', 'monthly']) ? $request->input('period') : 'monthly';
        $year    = (int) ($request->input('year', date('Y')));
        $lineId  = $request->input('line_id') ? (int) $request->input('line_id') : null;
        $month   = $request->input('month') ? max(1, min(12, (int) $request->input('month'))) : (int) date('n');
        $week    = $request->input('week')  ? max(1, min(53, (int) $request->input('week')))  : (int) date('W');

        // Validate year range to prevent abuse
        $year = max(2000, min((int) date('Y') + 1, $year));

        $lines = Line::orderBy('name')->get();

        // --- Base query builder helper ---
        // Supports both explicit production_year/month_number/week_number fields
        // AND fallback to due_date when production period fields are not set.
        $workOrderQuery = function () use ($period, $year, $month, $week, $lineId) {
            $q = DB::table('work_orders');

            if ($period === 'monthly') {
                $q->where(function ($sub) use ($year, $month) {
                    $sub->where(function ($s) use ($year, $month) {
                        $s->where('production_year', $year)->where('month_number', $month);
                    })->orWhere(function ($s) use ($year, $month) {
                        $s->whereNull('production_year')
                          ->whereNotNull('due_date')
                          ->whereYear('due_date', $year)
                          ->whereMonth('due_date', $month);
                    });
                });
            } else {
                $q->where(function ($sub) use ($year, $week) {
                    $sub->where(function ($s) use ($year, $week) {
                        $s->where('production_year', $year)->where('week_number', $week);
                    })->orWhere(function ($s) use ($year, $week) {
                        $s->whereNull('production_year')
                          ->whereNotNull('due_date')
                          ->whereYear('due_date', $year)
                          ->whereRaw('EXTRACT(WEEK FROM due_date) = ?', [$week]);
                    });
                });
            }

            if ($lineId) {
                $q->where('line_id', $lineId);
            }

            return $q;
        };

        // --- KPI: Summary ---
        $totalWorkOrders = (clone $workOrderQuery())->count();

        $completedWorkOrders = (clone $workOrderQuery())
            ->where('status', 'DONE')
            ->count();

        $completionRate = $totalWorkOrders > 0
            ? round(($completedWorkOrders / $totalWorkOrders) * 100, 1)
            : 0;

        $totalProducedQty = (clone $workOrderQuery())->sum('produced_qty');

        // Average cycle time (minutes) for completed batches linked to work orders in period
        $workOrderIds = (clone $workOrderQuery())->pluck('id');

        $cycleExpr = match (DB::getDriverName()) {
            'pgsql'  => 'AVG(EXTRACT(EPOCH FROM (completed_at - started_at)) / 60) as avg_minutes',
            'sqlite' => "AVG((strftime('%s', completed_at) - strftime('%s', started_at)) / 60.0) as avg_minutes",
            default  => 'AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at) / 60) as avg_minutes',
        };

        $avgCycleTime = DB::table('batches')
            ->whereIn('work_order_id', $workOrderIds)
            ->where('status', 'DONE')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw($cycleExpr)
            ->value('avg_minutes');

        $avgCycleTime = $avgCycleTime !== null ? round((float) $avgCycleTime, 1) : null;

        // --- Production by line ---
        $byLineBase = (clone $workOrderQuery())->whereNotNull('line_id');
        $byLineIds  = $byLineBase->pluck('id');

        $byLine = DB::table('work_orders')
            ->join('lines', 'work_orders.line_id', '=', 'lines.id')
            ->whereIn('work_orders.id', $byLineIds)
            ->select(
                'lines.name as line_name',
                DB::raw('SUM(work_orders.planned_qty) as planned_qty'),
                DB::raw('SUM(work_orders.produced_qty) as produced_qty'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("SUM(CASE WHEN work_orders.status = 'DONE' THEN 1 ELSE 0 END) as completed_orders")
            )
            ->groupBy('lines.id', 'lines.name')
            ->orderBy('lines.name')
            ->get()
            ->map(function ($row) {
                $row->completion_pct = $row->total_orders > 0
                    ? round(($row->completed_orders / $row->total_orders) * 100, 1)
                    : 0;
                return $row;
            });

        // --- Work orders by status ---
        $byStatusIds = (clone $workOrderQuery())->pluck('id');

        $byStatus = DB::table('work_orders')
            ->whereIn('id', $byStatusIds)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(function ($row) use ($totalWorkOrders) {
                $row->pct = $totalWorkOrders > 0
                    ? round(($row->count / $totalWorkOrders) * 100, 1)
                    : 0;
                return $row;
            });

        // --- Top 5 issues by type ---
        $topIssues = DB::table('issues')
            ->join('issue_types', 'issues.issue_type_id', '=', 'issue_types.id')
            ->whereIn('issues.work_order_id', $workOrderIds)
            ->select('issue_types.name as type_name', DB::raw('COUNT(*) as count'))
            ->groupBy('issue_types.id', 'issue_types.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Available years for filter (past 5 years + current + next)
        $currentYear = (int) date('Y');
        $availableYears = range($currentYear - 4, $currentYear + 1);

        return view('admin.reports.index', compact(
            'period',
            'year',
            'month',
            'week',
            'lineId',
            'lines',
            'availableYears',
            'totalWorkOrders',
            'completedWorkOrders',
            'completionRate',
            'totalProducedQty',
            'avgCycleTime',
            'byLine',
            'byStatus',
            'topIssues'
        ));
    }
}
