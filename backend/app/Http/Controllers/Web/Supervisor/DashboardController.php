<?php

namespace App\Http\Controllers\Web\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\Line;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $lineId = $request->query('line_id');

        // Get all active lines for dropdown
        $lines = Line::where('is_active', true)->get();

        // If line_id not specified, use first line
        if (!$lineId && $lines->isNotEmpty()) {
            $lineId = $lines->first()->id;
        }

        $selectedLine = $lineId ? Line::find($lineId) : null;

        // Get overview statistics
        $stats = $this->getOverviewStats($lineId);

        // Get throughput data (last 30 days)
        $throughputData = $this->getThroughputData($lineId);

        // Get cycle time data
        $cycleTimeData = $this->getCycleTimeData($lineId);

        // Get issue statistics
        $issueStats = $this->getIssueStats($lineId);

        // Get recent issues
        $recentIssues = $this->getRecentIssues($lineId);

        return view('supervisor.dashboard', compact(
            'lines',
            'selectedLine',
            'stats',
            'throughputData',
            'cycleTimeData',
            'issueStats',
            'recentIssues'
        ));
    }

    protected function getOverviewStats($lineId)
    {
        $query = WorkOrder::query();
        if ($lineId) {
            $query->where('line_id', $lineId);
        }

        return [
            'total_work_orders' => (clone $query)->count(),
            'active_work_orders' => (clone $query)->whereIn('status', [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_IN_PROGRESS])->count(),
            'completed_work_orders' => (clone $query)->where('status', WorkOrder::STATUS_DONE)->count(),
            'blocked_work_orders' => (clone $query)->where('status', WorkOrder::STATUS_BLOCKED)->count(),
            'open_issues' => Issue::where('status', 'OPEN')
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
                })->count(),
            'blocking_issues' => Issue::where('status', 'OPEN')
                ->whereHas('issueType', fn($q) => $q->where('is_blocking', true))
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
                })->count(),
        ];
    }

    protected function getThroughputData($lineId, $days = 30)
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $dailyProduction = WorkOrder::selectRaw('DATE(updated_at) as date, SUM(produced_qty) as total_produced')
            ->when($lineId, fn($q) => $q->where('line_id', $lineId))
            ->whereBetween('updated_at', [$startDate, Carbon::now()->endOfDay()])
            ->where('produced_qty', '>', 0)
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get();

        return [
            'labels' => $dailyProduction->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->toArray(),
            'values' => $dailyProduction->pluck('total_produced')->toArray(),
            'average' => round($dailyProduction->avg('total_produced'), 2),
        ];
    }

    protected function getCycleTimeData($lineId, $days = 30)
    {
        $completedBatches = Batch::where('status', Batch::STATUS_DONE)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where('completed_at', '>=', Carbon::now()->subDays($days))
            ->with('workOrder.productType')
            ->get();

        return $completedBatches->map(function ($batch) {
            $cycleTimeMinutes = (int) abs(
                Carbon::parse($batch->started_at)->diffInMinutes(Carbon::parse($batch->completed_at))
            );

            return [
                'batch_number' => $batch->batch_number,
                'work_order_no' => $batch->workOrder->order_no,
                'product_type' => optional($batch->workOrder->productType)->name ?? '—',
                'produced_qty' => $batch->produced_qty,
                'cycle_time_minutes' => $cycleTimeMinutes,
                'cycle_time_hours' => round($cycleTimeMinutes / 60, 2),
                'completed_at' => $batch->completed_at,
            ];
        });
    }

    protected function getIssueStats($lineId, $days = 30)
    {
        $startDate = Carbon::now()->subDays($days);

        $issuesByType = Issue::selectRaw('issue_types.name as type_name, COUNT(*) as count')
            ->join('issue_types', 'issues.issue_type_id', '=', 'issue_types.id')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where('issues.reported_at', '>=', $startDate)
            ->groupBy('issue_types.name')
            ->get();

        $issuesByStatus = Issue::selectRaw('status, COUNT(*) as count')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where('reported_at', '>=', $startDate)
            ->groupBy('status')
            ->get();

        return [
            'by_type' => [
                'labels' => $issuesByType->pluck('type_name')->toArray(),
                'values' => $issuesByType->pluck('count')->toArray(),
            ],
            'by_status' => [
                'labels' => $issuesByStatus->pluck('status')->toArray(),
                'values' => $issuesByStatus->pluck('count')->toArray(),
            ],
        ];
    }

    protected function getRecentIssues($lineId, $limit = 10)
    {
        return Issue::with(['workOrder', 'issueType', 'reportedBy'])
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->orderBy('reported_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
