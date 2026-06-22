<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\Line;
use App\Models\WorkOrder;
use App\Services\Material\NetRequirementsService;
use App\Services\Scrap\ScrapReportService;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Generate production summary report
     */
    public function productionSummary(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'line_id' => 'nullable|exists:lines,id',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $lineId = $request->line_id;

        $query = WorkOrder::whereBetween('created_at', [$startDate, $endDate]);
        if ($lineId) {
            $query->where('line_id', $lineId);
        }

        $workOrders = $query->with(['productType', 'line'])->get();

        $summary = [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'line' => $lineId ? Line::find($lineId)->name : 'All Lines',
            'work_orders' => [
                'total' => $workOrders->count(),
                'completed' => $workOrders->where('status', 'DONE')->count(),
                'in_progress' => $workOrders->where('status', 'IN_PROGRESS')->count(),
                'pending' => $workOrders->where('status', 'PENDING')->count(),
                'blocked' => $workOrders->where('status', 'BLOCKED')->count(),
                'cancelled' => $workOrders->where('status', 'CANCELLED')->count(),
            ],
            'production' => [
                'total_planned' => $workOrders->sum('planned_qty'),
                'total_produced' => $workOrders->sum('produced_qty'),
                'completion_rate' => $workOrders->sum('planned_qty') > 0
                    ? round(($workOrders->sum('produced_qty') / $workOrders->sum('planned_qty')) * 100, 2)
                    : 0,
            ],
            'by_product_type' => $workOrders->groupBy('product_type_id')->map(function ($orders) {
                return [
                    'product_type' => $orders->first()->productType->name,
                    'total_orders' => $orders->count(),
                    'planned_qty' => $orders->sum('planned_qty'),
                    'produced_qty' => $orders->sum('produced_qty'),
                ];
            })->values(),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()->json(['data' => $summary]);
    }

    /**
     * Generate batch completion report
     */
    public function batchCompletion(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'line_id' => 'nullable|exists:lines,id',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $query = Batch::whereBetween('completed_at', [$startDate, $endDate])
            ->where('status', 'DONE');

        if ($request->line_id) {
            $query->whereHas('workOrder', fn ($q) => $q->where('line_id', $request->line_id));
        }

        $batches = $query->with(['workOrder.productType', 'workOrder.line'])->get();

        $report = [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_batches' => $batches->count(),
                'total_produced' => $batches->sum('produced_qty'),
                'average_batch_size' => $batches->avg('target_qty'),
            ],
            'batches' => $batches->map(function ($batch) {
                $cycleTime = $batch->started_at && $batch->completed_at
                    ? Carbon::parse($batch->started_at)->diffInMinutes($batch->completed_at)
                    : null;

                return [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'work_order_no' => $batch->workOrder->order_no,
                    'product_type' => $batch->workOrder->productType->name,
                    'line' => $batch->workOrder->line->name,
                    'target_qty' => $batch->target_qty,
                    'produced_qty' => $batch->produced_qty,
                    'started_at' => $batch->started_at,
                    'completed_at' => $batch->completed_at,
                    'cycle_time_minutes' => $cycleTime,
                    'cycle_time_hours' => $cycleTime ? round($cycleTime / 60, 2) : null,
                ];
            }),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()->json(['data' => $report]);
    }

    /**
     * Generate downtime/issue report
     */
    public function downtimeReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'line_id' => 'nullable|exists:lines,id',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $query = Issue::whereBetween('reported_at', [$startDate, $endDate]);

        if ($request->line_id) {
            $query->whereHas('workOrder', fn ($q) => $q->where('line_id', $request->line_id));
        }

        $issues = $query->with(['issueType', 'workOrder', 'reportedBy'])->get();

        $downtimeMinutes = $issues->filter(fn ($issue) => $issue->resolved_at && $issue->reported_at)
            ->sum(function ($issue) {
                return Carbon::parse($issue->reported_at)->diffInMinutes($issue->resolved_at);
            });

        $report = [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_issues' => $issues->count(),
                'open_issues' => $issues->where('status', 'OPEN')->count(),
                'resolved_issues' => $issues->where('status', 'RESOLVED')->count(),
                'closed_issues' => $issues->where('status', 'CLOSED')->count(),
                'total_downtime_minutes' => $downtimeMinutes,
                'total_downtime_hours' => round($downtimeMinutes / 60, 2),
                'average_resolution_time_minutes' => $issues->count() > 0
                    ? round($downtimeMinutes / $issues->count(), 2)
                    : 0,
            ],
            'by_type' => $issues->groupBy('issue_type_id')->map(function ($typeIssues) {
                $typeDowntime = $typeIssues->filter(fn ($i) => $i->resolved_at && $i->reported_at)
                    ->sum(fn ($i) => Carbon::parse($i->reported_at)->diffInMinutes($i->resolved_at));

                return [
                    'type' => $typeIssues->first()->issueType->name,
                    'count' => $typeIssues->count(),
                    'downtime_minutes' => $typeDowntime,
                    'downtime_hours' => round($typeDowntime / 60, 2),
                ];
            })->values(),
            'issues' => $issues->map(function ($issue) {
                $downtime = $issue->resolved_at && $issue->reported_at
                    ? Carbon::parse($issue->reported_at)->diffInMinutes($issue->resolved_at)
                    : null;

                return [
                    'id' => $issue->id,
                    'title' => $issue->title,
                    'type' => $issue->issueType->name,
                    'status' => $issue->status,
                    'work_order_no' => $issue->workOrder->order_no,
                    'reported_at' => $issue->reported_at,
                    'resolved_at' => $issue->resolved_at,
                    'downtime_minutes' => $downtime,
                ];
            }),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()->json(['data' => $report]);
    }

    /**
     * Pareto data by scrap reason (descending scrap quantity, cumulative share).
     */
    public function scrapPareto(Request $request, ScrapReportService $service): JsonResponse
    {
        [$from, $to, $lineId] = $this->scrapReportRange($request);

        return response()->json(['data' => [
            'period' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'line_id' => $lineId,
            'pareto' => $service->pareto($from, $to, $lineId),
            'by_category' => $service->byCategory($from, $to, $lineId),
            'generated_at' => now()->toIso8601String(),
        ]]);
    }

    /**
     * Scrap rate per line over time (scrap qty / total produced) plus daily trend.
     */
    public function scrapRate(Request $request, ScrapReportService $service): JsonResponse
    {
        [$from, $to, $lineId] = $this->scrapReportRange($request);

        return response()->json(['data' => [
            'period' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'per_line' => $service->ratePerLine($from, $to),
            'trend' => $service->trend($from, $to, $lineId),
            'generated_at' => now()->toIso8601String(),
        ]]);
    }

    /**
     * MRP net requirements + shortage report (#90): explode planned work orders
     * against BOMs, net against on-hand stock. Forward-looking: defaults to the
     * next 30 days.
     */
    public function netRequirements(Request $request, NetRequirementsService $service): JsonResponse
    {
        $validated = $request->validate([
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $from = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : today()->startOfDay();
        $to = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : today()->addDays(30)->endOfDay();
        $lineId = isset($validated['line_id']) ? (int) $validated['line_id'] : null;

        return response()->json([
            'data' => array_merge(
                $service->report($from, $to, $lineId),
                ['generated_at' => now()->toIso8601String()],
            ),
        ]);
    }

    /**
     * Resolve and validate the [from, to, lineId] window for scrap reports.
     * Defaults to the last 30 days when no dates are supplied.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int|null}
     */
    private function scrapReportRange(Request $request): array
    {
        $validated = $request->validate([
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $from = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : today()->subDays(29)->startOfDay();
        $to = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : today()->endOfDay();
        $lineId = isset($validated['line_id']) ? (int) $validated['line_id'] : null;

        return [$from, $to, $lineId];
    }

    /**
     * Export report as CSV
     */
    public function exportCsv(Request $request)
    {
        $reportType = $request->input('report_type', 'production_summary');

        // Generate the appropriate report
        $reportData = match ($reportType) {
            'production_summary' => $this->productionSummary($request)->getData()->data,
            'batch_completion' => $this->batchCompletion($request)->getData()->data,
            'downtime' => $this->downtimeReport($request)->getData()->data,
            default => null,
        };

        if (! $reportData) {
            return response()->json(['error' => 'Invalid report type'], 400);
        }

        // Convert to CSV format
        $csv = $this->convertReportToCsv($reportData, $reportType);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$reportType.'_'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function convertReportToCsv($reportData, $reportType): string
    {
        $csv = '';

        // Add headers based on report type
        if ($reportType === 'batch_completion' && isset($reportData->batches)) {
            $csv .= Csv::row([
                'Batch ID',
                'Batch Number',
                'Work Order',
                'Product Type',
                'Line',
                'Target Qty',
                'Produced Qty',
                'Started At',
                'Completed At',
                'Cycle Time (hours)',
            ]);

            foreach ($reportData->batches as $batch) {
                $csv .= Csv::row([
                    $batch->batch_id,
                    $batch->batch_number,
                    $batch->work_order_no,
                    $batch->product_type,
                    $batch->line,
                    $batch->target_qty,
                    $batch->produced_qty,
                    $batch->started_at,
                    $batch->completed_at,
                    $batch->cycle_time_hours,
                ]);
            }
        } elseif ($reportType === 'downtime' && isset($reportData->issues)) {
            $csv .= Csv::row([
                'Issue ID',
                'Title',
                'Type',
                'Status',
                'Work Order',
                'Reported At',
                'Resolved At',
                'Downtime (minutes)',
            ]);

            foreach ($reportData->issues as $issue) {
                $csv .= Csv::row([
                    $issue->id,
                    $issue->title,
                    $issue->type,
                    $issue->status,
                    $issue->work_order_no,
                    $issue->reported_at,
                    $issue->resolved_at ?? 'N/A',
                    $issue->downtime_minutes ?? 'N/A',
                ]);
            }
        }

        return $csv;
    }
}
