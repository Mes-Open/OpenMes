<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\IssueType;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Show work order queue for selected line.
     */
    public function queue(Request $request)
    {
        $lineId = $request->session()->get('selected_line_id');

        if (!$lineId) {
            return redirect()->route('operator.select-line')
                ->with('error', 'Please select a line first.');
        }

        // Get active and completed work orders for this line
        $activeWorkOrders = WorkOrder::where('line_id', $lineId)
            ->whereIn('status', [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_BLOCKED])
            ->with(['productType', 'batches'])
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc')
            ->get();

        $completedWorkOrders = WorkOrder::where('line_id', $lineId)
            ->where('status', WorkOrder::STATUS_DONE)
            ->with(['productType', 'batches'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $line = \App\Models\Line::find($lineId);

        return view('operator.queue', compact('activeWorkOrders', 'completedWorkOrders', 'line'));
    }

    /**
     * Show work order detail page.
     */
    public function show(Request $request, WorkOrder $workOrder)
    {
        $lineId = $request->session()->get('selected_line_id');

        // Verify work order belongs to selected line
        if ($workOrder->line_id != $lineId) {
            return redirect()->route('operator.queue')
                ->with('error', 'This work order does not belong to the selected line.');
        }

        $workOrder->load([
            'line',
            'productType',
            'batches.steps.startedBy',
            'batches.steps.completedBy',
            'issues.issueType',
            'issues.reportedBy',
        ]);

        $issueTypes = IssueType::where('is_active', true)->orderBy('name')->get();

        return view('operator.work-order-detail', compact('workOrder', 'issueTypes'));
    }
}
