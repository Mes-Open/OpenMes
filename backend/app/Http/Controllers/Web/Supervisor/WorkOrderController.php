<?php

namespace App\Http\Controllers\Web\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkOrder::with(['line', 'productType'])->withCount('batches')
            ->orderByRaw("CASE status
                WHEN 'BLOCKED'     THEN 1
                WHEN 'IN_PROGRESS' THEN 2
                WHEN 'PAUSED'      THEN 3
                WHEN 'PENDING'     THEN 4
                WHEN 'ACCEPTED'    THEN 5
                WHEN 'DONE'        THEN 6
                ELSE 7 END")
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('line_id')) {
            $query->where('line_id', $request->line_id);
        }
        if ($request->filled('search')) {
            $query->where('order_no', 'ilike', '%' . $request->search . '%');
        }

        $workOrders = $query->paginate(25)->withQueryString();
        $lines      = Line::orderBy('name')->get();

        return view('supervisor.work-orders.index', compact('workOrders', 'lines'));
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(['line', 'productType', 'batches.steps', 'issues.issueType', 'issues.reportedBy']);
        return view('supervisor.work-orders.show', compact('workOrder'));
    }

    public function accept(WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Only PENDING work orders can be accepted.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_ACCEPTED]);
        return redirect()->back()->with('success', "Work order {$workOrder->order_no} accepted.");
    }

    public function reject(WorkOrder $workOrder)
    {
        if (!in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])) {
            return redirect()->back()->with('error', 'Only PENDING or ACCEPTED work orders can be rejected.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_REJECTED]);
        return redirect()->back()->with('success', "Work order {$workOrder->order_no} rejected.");
    }

    public function pause(WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
            return redirect()->back()->with('error', 'Only IN_PROGRESS work orders can be paused.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_PAUSED]);
        return redirect()->back()->with('success', "Work order {$workOrder->order_no} paused.");
    }

    public function resume(WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_PAUSED) {
            return redirect()->back()->with('error', 'Only PAUSED work orders can be resumed.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        return redirect()->back()->with('success', "Work order {$workOrder->order_no} resumed.");
    }
}
