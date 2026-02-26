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
        $workOrderQuery = function () use ($period, $year, $month, $week, $lineId) {
            $q = DB::table('work_orders')->where('production_year', $year);

            if ($period === 'monthly') {
                $q->where('month_number', $month);
            } else {
                $q->where('week_number', $week);
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
        $byLine = DB::table('work_orders')
            ->join('lines', 'work_orders.line_id', '=', 'lines.id')
            ->where('work_orders.production_year', $year)
            ->when($period === 'monthly', fn ($q) => $q->where('work_orders.month_number', $month))
            ->when($period === 'weekly',  fn ($q) => $q->where('work_orders.week_number', $week))
            ->when($lineId, fn ($q) => $q->where('work_orders.line_id', $lineId))
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
        $byStatus = DB::table('work_orders')
            ->where('production_year', $year)
            ->when($period === 'monthly', fn ($q) => $q->where('month_number', $month))
            ->when($period === 'weekly',  fn ($q) => $q->where('week_number', $week))
            ->when($lineId, fn ($q) => $q->where('line_id', $lineId))
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
