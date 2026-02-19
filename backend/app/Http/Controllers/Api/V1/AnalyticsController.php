<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\BatchStep;
use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get overview statistics for supervisor dashboard
     */
    public function overview(Request $request)
    {
        $lineId = $request->query('line_id');

        $query = WorkOrder::query();
        if ($lineId) {
            $query->where('line_id', $lineId);
        }

        // Overall statistics
        $stats = [
            'total_work_orders' => (clone $query)->count(),
            'active_work_orders' => (clone $query)->whereIn('status', ['PENDING', 'IN_PROGRESS'])->count(),
            'completed_work_orders' => (clone $query)->where('status', 'DONE')->count(),
            'blocked_work_orders' => (clone $query)->where('status', 'BLOCKED')->count(),

            'total_batches' => Batch::when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })->count(),

            'active_batches' => Batch::where('status', 'IN_PROGRESS')
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
                })->count(),

            'open_issues' => Issue::where('status', 'OPEN')
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
                })->count(),

            'critical_issues' => Issue::where('status', 'OPEN')
                ->whereHas('issueType', fn($q) => $q->where('severity', 'CRITICAL'))
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
                })->count(),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get production metrics by line
     */
    public function productionByLine(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());

        $metrics = Line::with(['workOrders' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }])
        ->get()
        ->map(function ($line) {
            return [
                'line_id' => $line->id,
                'line_name' => $line->name,
                'line_code' => $line->code,
                'total_work_orders' => $line->workOrders->count(),
                'completed' => $line->workOrders->where('status', 'DONE')->count(),
                'in_progress' => $line->workOrders->where('status', 'IN_PROGRESS')->count(),
                'pending' => $line->workOrders->where('status', 'PENDING')->count(),
                'blocked' => $line->workOrders->where('status', 'BLOCKED')->count(),
                'total_planned_qty' => $line->workOrders->sum('planned_qty'),
                'total_produced_qty' => $line->workOrders->sum('produced_qty'),
            ];
        });

        return response()->json(['data' => $metrics]);
    }

    /**
     * Get cycle time statistics
     */
    public function cycleTime(Request $request)
    {
        $lineId = $request->query('line_id');
        $days = $request->query('days', 30);

        $completedBatches = Batch::where('status', 'DONE')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where('completed_at', '>=', Carbon::now()->subDays($days))
            ->with('workOrder.productType')
            ->get();

        $cycleTimeData = $completedBatches->map(function ($batch) {
            $startedAt = Carbon::parse($batch->started_at);
            $completedAt = Carbon::parse($batch->completed_at);
            $cycleTimeMinutes = $startedAt->diffInMinutes($completedAt);

            return [
                'batch_id' => $batch->id,
                'work_order_no' => $batch->workOrder->order_no,
                'product_type' => $batch->workOrder->productType->name,
                'target_qty' => $batch->target_qty,
                'produced_qty' => $batch->produced_qty,
                'cycle_time_minutes' => $cycleTimeMinutes,
                'cycle_time_hours' => round($cycleTimeMinutes / 60, 2),
                'completed_at' => $batch->completed_at,
            ];
        });

        $avgCycleTime = $cycleTimeData->avg('cycle_time_minutes');

        return response()->json([
            'data' => [
                'batches' => $cycleTimeData->values(),
                'average_cycle_time_minutes' => round($avgCycleTime, 2),
                'average_cycle_time_hours' => round($avgCycleTime / 60, 2),
                'total_batches' => $cycleTimeData->count(),
            ]
        ]);
    }

    /**
     * Get throughput metrics (units per day)
     */
    public function throughput(Request $request)
    {
        $lineId = $request->query('line_id');
        $days = $request->query('days', 30);

        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $dailyProduction = WorkOrder::selectRaw('DATE(updated_at) as date, SUM(produced_qty) as total_produced')
            ->when($lineId, fn($q) => $q->where('line_id', $lineId))
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->where('produced_qty', '>', 0)
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get();

        $avgThroughput = $dailyProduction->avg('total_produced');

        return response()->json([
            'data' => [
                'daily_production' => $dailyProduction,
                'average_daily_throughput' => round($avgThroughput, 2),
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
            ]
        ]);
    }

    /**
     * Get issue statistics and trends
     */
    public function issueStats(Request $request)
    {
        $lineId = $request->query('line_id');
        $days = $request->query('days', 30);

        $startDate = Carbon::now()->subDays($days);

        // Issues by type
        $issuesByType = Issue::selectRaw('issue_types.name as type_name, COUNT(*) as count')
            ->join('issue_types', 'issues.issue_type_id', '=', 'issue_types.id')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where('issues.reported_at', '>=', $startDate)
            ->groupBy('issue_types.name')
            ->get();

        // Issues by status
        $issuesByStatus = Issue::selectRaw('status, COUNT(*) as count')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where('reported_at', '>=', $startDate)
            ->groupBy('status')
            ->get();

        // Average resolution time
        $avgResolutionTime = Issue::whereNotNull('resolved_at')
            ->whereNotNull('reported_at')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where('reported_at', '>=', $startDate)
            ->get()
            ->map(function ($issue) {
                return Carbon::parse($issue->reported_at)->diffInMinutes($issue->resolved_at);
            })
            ->avg();

        return response()->json([
            'data' => [
                'by_type' => $issuesByType,
                'by_status' => $issuesByStatus,
                'average_resolution_time_minutes' => round($avgResolutionTime ?? 0, 2),
                'average_resolution_time_hours' => round(($avgResolutionTime ?? 0) / 60, 2),
            ]
        ]);
    }

    /**
     * Get step performance metrics
     */
    public function stepPerformance(Request $request)
    {
        $lineId = $request->query('line_id');
        $days = $request->query('days', 30);

        $startDate = Carbon::now()->subDays($days);

        $stepStats = BatchStep::where('status', 'DONE')
            ->whereNotNull('duration_minutes')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('batch.workOrder', fn($wo) => $wo->where('line_id', $lineId));
            })
            ->where(function ($q) use ($startDate) {
                $q->whereNull('completed_at')
                    ->orWhere('completed_at', '>=', $startDate);
            })
            ->selectRaw('name, AVG(duration_minutes) as avg_duration, COUNT(*) as count')
            ->groupBy('name')
            ->orderBy('avg_duration', 'desc')
            ->get();

        return response()->json([
            'data' => $stepStats->map(function ($stat) {
                return [
                    'step_name' => $stat->name,
                    'average_duration_minutes' => round($stat->avg_duration, 2),
                    'total_completions' => $stat->count,
                ];
            })
        ]);
    }
}
