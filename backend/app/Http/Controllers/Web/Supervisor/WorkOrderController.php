<?php

namespace App\Http\Controllers\Web\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\ProductType;
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

    public function complete(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
            return redirect()->back()->with('error', 'Only IN_PROGRESS work orders can be completed.');
        }

        $validated = $request->validate([
            'produced_qty' => 'required|numeric|min:0.01',
        ]);

        $workOrder->update([
            'status'       => WorkOrder::STATUS_DONE,
            'produced_qty' => $validated['produced_qty'],
            'completed_at' => now(),
        ]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} completed.");
    }

    public function cancel(WorkOrder $workOrder)
    {
        if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()->with('error', 'Cannot cancel a work order in terminal state.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_CANCELLED]);
        return redirect()->back()->with('success', "Work order {$workOrder->order_no} cancelled.");
    }

    public function reopen(WorkOrder $workOrder)
    {
        if (!in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()->with('error', 'Only terminal work orders can be reopened.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        return redirect()->back()->with('success', "Work order {$workOrder->order_no} reopened.");
    }

    public function edit(WorkOrder $workOrder)
    {
        $lines        = Line::where('is_active', true)->orderBy('name')->get();
        $productTypes = ProductType::where('is_active', true)->orderBy('name')->get();

        return view('admin.work-orders.edit', compact('workOrder', 'lines', 'productTypes'));
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $validated = $request->validate([
            'order_no'        => 'required|string|max:100|unique:work_orders,order_no,' . $workOrder->id,
            'line_id'         => 'nullable|exists:lines,id',
            'product_type_id' => 'nullable|exists:product_types,id',
            'planned_qty'     => 'required|numeric|min:0.01',
            'priority'        => 'nullable|integer|min:0|max:100',
            'due_date'        => 'nullable|date',
            'description'     => 'nullable|string|max:2000',
            'status'          => 'required|in:PENDING,ACCEPTED,IN_PROGRESS,PAUSED,BLOCKED,DONE,REJECTED,CANCELLED',
        ]);

        $workOrder->update($validated);

        return redirect()->route('supervisor.work-orders.show', $workOrder)
            ->with('success', "Work order {$workOrder->order_no} updated.");
    }
}
