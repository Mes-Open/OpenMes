<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Create a new batch for a work order.
     */
    public function store(Request $request)
    {
        $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'target_qty' => 'required|numeric|min:0.01',
        ]);

        $workOrder = WorkOrder::find($request->input('work_order_id'));

        // Verify work order belongs to selected line
        if ($workOrder->line_id != $request->session()->get('selected_line_id')) {
            return back()->with('error', 'This work order does not belong to the selected line.');
        }

        // Create batch
        try {
            $batch = $this->workOrderService->createBatch(
                $workOrder,
                $request->input('target_qty')
            );

            return redirect()->route('operator.work-order.detail', $workOrder)
                ->with('success', 'Batch created successfully.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to create batch: ' . $e->getMessage())
                ->withInput();
        }
    }
}
