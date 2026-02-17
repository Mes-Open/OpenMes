<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            $query->whereHas('workOrder', fn($q) => $q->where('line_id', $request->line_id));
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
            $query->whereHas('workOrder', fn($q) => $q->where('line_id', $request->line_id));
        }

        $issues = $query->with(['issueType', 'workOrder', 'reportedBy'])->get();

        $downtimeMinutes = $issues->filter(fn($issue) => $issue->resolved_at && $issue->reported_at)
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
                $typeDowntime = $typeIssues->filter(fn($i) => $i->resolved_at && $i->reported_at)
                    ->sum(fn($i) => Carbon::parse($i->reported_at)->diffInMinutes($i->resolved_at));

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

        if (!$reportData) {
            return response()->json(['error' => 'Invalid report type'], 400);
        }

        // Convert to CSV format
        $csv = $this->convertReportToCsv($reportData, $reportType);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $reportType . '_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    private function convertReportToCsv($reportData, $reportType): string
    {
        $output = fopen('php://temp', 'r+');

        // Add headers based on report type
        if ($reportType === 'batch_completion' && isset($reportData->batches)) {
            fputcsv($output, [
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
                fputcsv($output, [
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
            fputcsv($output, [
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
                fputcsv($output, [
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

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
