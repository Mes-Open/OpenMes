<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperatorRatesRequest;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\Line;
use App\Models\WorkOrder;
use App\Services\Production\OperatorProductionRateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $todayStart = Carbon::now()->startOfDay();

        $stats = [
            // Work orders
            'total_work_orders' => (clone $query)->count(),
            'pending_work_orders' => (clone $query)->where('status', 'PENDING')->count(),
            // "In progress (incl. accepted)" matches the web dashboard label.
            'in_progress_work_orders' => (clone $query)->whereIn('status', ['ACCEPTED', 'IN_PROGRESS'])->count(),
            'active_work_orders' => (clone $query)->whereIn('status', ['PENDING', 'ACCEPTED', 'IN_PROGRESS'])->count(),
            'completed_work_orders' => (clone $query)->where('status', 'DONE')->count(),
            'done_today_work_orders' => (clone $query)
                ->where('status', 'DONE')
                ->where('updated_at', '>=', $todayStart)
                ->count(),
            'blocked_work_orders' => (clone $query)->where('status', 'BLOCKED')->count(),

            // Batches
            'total_batches' => Batch::when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
            })->count(),
            'active_batches' => Batch::where('status', 'IN_PROGRESS')
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
                })->count(),

            // Issues
            'open_issues' => Issue::where('status', 'OPEN')
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
                })->count(),
            // Issues whose type is_blocking AND status is OPEN/ACKNOWLEDGED.
            'blocking_issues' => Issue::whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
                ->whereHas('issueType', fn ($q) => $q->where('is_blocking', true))
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
                })->count(),
            'critical_issues' => Issue::where('status', 'OPEN')
                ->whereHas('issueType', fn ($q) => $q->where('severity', 'CRITICAL'))
                ->when($lineId, function ($q) use ($lineId) {
                    $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
                })->count(),

            // Lines with at least one active (PENDING/ACCEPTED/IN_PROGRESS/BLOCKED) work order
            'active_lines' => Line::where('is_active', true)
                ->whereHas('workOrders', fn ($q) => $q->whereIn('status', ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'BLOCKED']))
                ->when($lineId, fn ($q) => $q->where('id', $lineId))
                ->count(),
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
                $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
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
            ],
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
            ->when($lineId, fn ($q) => $q->where('line_id', $lineId))
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
            ],
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
                $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
            })
            ->where('issues.reported_at', '>=', $startDate)
            ->groupBy('issue_types.name')
            ->get();

        // Issues by status
        $issuesByStatus = Issue::selectRaw('status, COUNT(*) as count')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
            })
            ->where('reported_at', '>=', $startDate)
            ->groupBy('status')
            ->get();

        // Average resolution time
        $avgResolutionTime = Issue::whereNotNull('resolved_at')
            ->whereNotNull('reported_at')
            ->when($lineId, function ($q) use ($lineId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('line_id', $lineId));
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
            ],
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
                $q->whereHas('batch.workOrder', fn ($wo) => $wo->where('line_id', $lineId));
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
            }),
        ]);
    }

    /**
     * Operator production rate per machine (units/hour for each worker ×
     * workstation pair), derived from completed step events.
     *
     * List mode (default): every pair with history, fastest first, optionally
     * narrowed by line_id and a time window (days, or date_from / date_to;
     * omitted = all history). Single-pair mode (operator_id + workstation_id):
     * the one rate, or an explicit no-data state for a machine the worker has
     * never run.
     */
    public function operatorRates(OperatorRatesRequest $request, OperatorProductionRateService $service)
    {
        $operatorId = $request->filled('operator_id') ? (int) $request->query('operator_id') : null;
        $workstationId = $request->filled('workstation_id') ? (int) $request->query('workstation_id') : null;
        $lineId = $request->filled('line_id') ? (int) $request->query('line_id') : null;

        [$from, $to] = $this->resolveRateWindow($request);

        // Both ids given → resolve the single pair and report the no-data state
        // (a machine the worker has never run) explicitly.
        if ($operatorId && $workstationId) {
            $rate = $service->rateFor($operatorId, $workstationId, $from, $to);

            return response()->json([
                'data' => [
                    'operator_id' => $operatorId,
                    'workstation_id' => $workstationId,
                    'has_history' => $rate !== null,
                    'rate' => $rate,
                ],
            ]);
        }

        $rates = $service->rates($lineId, $from, $to, $operatorId, $workstationId);

        return response()->json([
            'data' => [
                'rates' => $rates->values(),
                'count' => $rates->count(),
            ],
        ]);
    }

    /**
     * Resolve the optional time window for rate queries: explicit
     * date_from/date_to, or a rolling `days` window, or all history.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveRateWindow(Request $request): array
    {
        if ($request->filled('date_from') || $request->filled('date_to')) {
            return [
                $request->filled('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : null,
                $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : null,
            ];
        }

        if ($request->filled('days')) {
            return [Carbon::now()->subDays((int) $request->query('days'))->startOfDay(), null];
        }

        return [null, null]; // all history
    }
}
