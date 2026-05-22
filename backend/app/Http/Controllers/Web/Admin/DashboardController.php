<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Line;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $lines = Line::where('is_active', true)->orderBy('name')->get();
        $selectedLineId = $request->query('line_id') ?: null;

        // Base queries — optionally scoped to a single line
        $woBase = WorkOrder::query();
        $issueBase = Issue::query();

        if ($selectedLineId) {
            $woBase->where('line_id', $selectedLineId);
            $issueBase->whereHas('workOrder', fn ($q) => $q->where('line_id', $selectedLineId));
        }

        $stats = [
            'total_work_orders' => (clone $woBase)->count(),
            'pending' => (clone $woBase)->where('status', WorkOrder::STATUS_PENDING)->count(),
            // ACCEPTED + IN_PROGRESS are both "active/running"
            'in_progress' => (clone $woBase)
                ->whereIn('status', [WorkOrder::STATUS_ACCEPTED, WorkOrder::STATUS_IN_PROGRESS])
                ->count(),
            'blocked' => (clone $woBase)->where('status', WorkOrder::STATUS_BLOCKED)->count(),
            'done' => (clone $woBase)->where('status', WorkOrder::STATUS_DONE)->count(),
            // Use completed_at (set explicitly on completion), not updated_at
            'done_today' => (clone $woBase)
                ->where('status', WorkOrder::STATUS_DONE)
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', today())
                ->count(),
            'open_issues' => (clone $issueBase)
                ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                ->count(),
            'blocking_issues' => (clone $issueBase)->blocking()->count(),
            'active_lines' => Line::where('is_active', true)->count(),
        ];

        $recentWorkOrders = (clone $woBase)
            ->with(['line', 'productType'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentIssues = (clone $issueBase)
            ->with(['workOrder', 'issueType', 'reportedBy'])
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Auto-calculate and fetch OEE for today/yesterday
        \Illuminate\Support\Facades\Cache::remember('oee_calculated_'.today()->toDateString(), 900, function () {
            $svc = app(\App\Services\Production\OeeCalculationService::class);
            $svc->calculateAll(today());
            $svc->calculateAll(\Carbon\Carbon::yesterday());

            return true;
        });

        $oeeRecords = \App\Models\OeeRecord::whereDate('record_date', today())
            ->orWhereDate('record_date', today()->subDay())
            ->orderByDesc('record_date')
            ->get()
            ->groupBy('line_id')
            ->map(fn ($records) => $records->first());

        $enabledWidgets = \App\Models\DashboardWidget::enabled()
            ->pluck('widget_id')
            ->toArray();

        $widgetOrder = \App\Models\DashboardWidget::orderBy('sort_order')
            ->where('enabled', true)
            ->pluck('widget_id')
            ->toArray();

        // Inbound QC widget data — computed only when the widget is enabled to
        // avoid extra queries on dashboards where the user has hidden it.
        $inboundQcStats = null;
        if (in_array('inbound_qc_overview', $enabledWidgets, true)) {
            $since = now()->subDays(29)->startOfDay(); // inclusive 30-day window
            $base = \App\Models\Inspection::where('started_at', '>=', $since);
            $completed = (clone $base)->whereIn('status', ['pass', 'fail', 'conditional_pass'])->count();
            $passed = (clone $base)->where('status', 'pass')->count();

            $inboundQcStats = [
                'pending' => \App\Models\Inspection::where('status', 'pending')->count(),
                'completed_30d' => $completed,
                'failed_30d' => (clone $base)->where('status', 'fail')->count(),
                'conditional_30d' => (clone $base)->where('status', 'conditional_pass')->count(),
                'pass_rate_30d' => $completed > 0 ? round(($passed / $completed) * 100, 1) : null,
                'recent_failures' => \App\Models\Inspection::with('material')
                    ->where('status', 'fail')
                    ->orderByDesc('completed_at')
                    ->limit(3)
                    ->get(),
            ];
        }

        // Materials Overview widget — low stock + expiring lots.
        $materialsStats = null;
        if (in_array('materials_overview', $enabledWidgets, true)) {
            $lowStock = \App\Models\Material::where('is_active', true)
                ->whereNotNull('min_stock_level')
                ->whereColumn('stock_quantity', '<=', 'min_stock_level')
                ->limit(5)
                ->get(['id', 'code', 'name', 'stock_quantity', 'min_stock_level', 'unit_of_measure']);

            $expiringSoon = \App\Models\MaterialLot::with('material:id,name,code,unit_of_measure')
                ->where('status', \App\Models\MaterialLot::STATUS_AVAILABLE)
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                ->orderBy('expiry_date')
                ->limit(5)
                ->get();

            $reservedTotal = (float) \App\Models\Material::sum('reserved_quantity');

            $materialsStats = [
                'low_stock_count' => \App\Models\Material::where('is_active', true)
                    ->whereNotNull('min_stock_level')
                    ->whereColumn('stock_quantity', '<=', 'min_stock_level')
                    ->count(),
                'low_stock_samples' => $lowStock,
                'expiring_count' => $expiringSoon->count(),
                'expiring_samples' => $expiringSoon,
                'reserved_total' => $reservedTotal,
                'lots_total' => \App\Models\MaterialLot::where('status', \App\Models\MaterialLot::STATUS_AVAILABLE)->count(),
                'quarantined_count' => \App\Models\MaterialLot::where('status', \App\Models\MaterialLot::STATUS_QUARANTINED)->count(),
            ];
        }

        return view('admin.dashboard', compact(
            'stats', 'recentWorkOrders', 'recentIssues',
            'lines', 'selectedLineId', 'oeeRecords', 'enabledWidgets', 'widgetOrder',
            'inboundQcStats', 'materialsStats'
        ));
    }
}
