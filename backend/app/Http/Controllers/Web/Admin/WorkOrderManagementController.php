<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;

class WorkOrderManagementController extends Controller
{
    public function __construct(protected WorkOrderService $workOrderService) {}

    public function index(Request $request)
    {
        $allowedSorts = ['order_no', 'status', 'planned_qty', 'produced_qty', 'priority', 'due_date', 'created_at'];
        $sort         = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'created_at';
        $direction    = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $query = WorkOrder::with(['line', 'productType'])->withCount('batches')
            ->orderBy($sort, $direction);

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

        return view('admin.work-orders.index', compact('workOrders', 'lines', 'sort', 'direction'));
    }

    public function create()
    {
        $lines        = Line::where('is_active', true)->orderBy('name')->get();
        $productTypes = ProductType::where('is_active', true)->orderBy('name')->get();

        return view('admin.work-orders.create', compact('lines', 'productTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_no'        => 'required|string|max:100|unique:work_orders,order_no',
            'line_id'         => 'nullable|exists:lines,id',
            'product_type_id' => 'nullable|exists:product_types,id',
            'planned_qty'     => 'required|numeric|min:0.01',
            'priority'        => 'nullable|integer|min:0|max:100',
            'due_date'        => 'nullable|date',
            'description'     => 'nullable|string|max:2000',
        ]);

        try {
            $workOrder = $this->workOrderService->createWorkOrder($validated);
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Failed to create work order: ' . $e->getMessage());
        }

        return redirect()->route('admin.work-orders.index')
            ->with('success', "Work order {$workOrder->order_no} created.");
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(['line', 'productType', 'batches.steps', 'issues.issueType', 'issues.reportedBy']);

        return view('admin.work-orders.show', compact('workOrder'));
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

        // Warn when marking as DONE with zero produced quantity
        if (($validated['status'] ?? '') === 'DONE' && (float) $workOrder->produced_qty <= 0) {
            return redirect()->back()->withInput()
                ->with('error', 'Cannot mark as DONE — produced quantity is 0. Register production first or adjust the quantity.');
        }

        $workOrder->update($validated);

        return redirect()->route('admin.work-orders.index')
            ->with('success', "Work order {$workOrder->order_no} updated.");
    }

    public function destroy(WorkOrder $workOrder)
    {
        if ($workOrder->batches()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete a work order that has batches. Cancel it instead.');
        }

        $no = $workOrder->order_no;
        $workOrder->delete();

        return redirect()->route('admin.work-orders.index')
            ->with('success', "Work order {$no} deleted.");
    }

    public function cancel(WorkOrder $workOrder)
    {
        if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()
                ->with('error', 'Cannot cancel a work order that is already in a terminal state.');
        }

        $workOrder->update(['status' => WorkOrder::STATUS_CANCELLED]);

        return redirect()->back()
            ->with('success', "Work order {$workOrder->order_no} cancelled.");
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

    public function reopen(WorkOrder $workOrder)
    {
        if (!in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()->with('error', 'Only terminal work orders (DONE, REJECTED, CANCELLED) can be reopened.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        return redirect()->back()->with('success', "Work order {$workOrder->order_no} reopened.");
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

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} completed with {$validated['produced_qty']} produced.");
    }
}
