<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnomalyReason;
use App\Models\ProductionAnomaly;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

class ProductionAnomalyController extends Controller
{
    /**
     * Display a listing of production anomalies.
     */
    public function index(Request $request)
    {
        $query = ProductionAnomaly::with(['workOrder', 'anomalyReason', 'createdBy'])
            ->orderBy('created_at', 'desc');

        if ($workOrderId = $request->input('work_order_id')) {
            $query->where('work_order_id', $workOrderId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($reasonId = $request->input('anomaly_reason_id')) {
            $query->where('anomaly_reason_id', $reasonId);
        }

        $anomalies     = $query->paginate(25)->withQueryString();
        $anomalyReasons = AnomalyReason::active()->orderBy('name')->get();

        // Pass a specific work order if filtered
        $workOrder = null;
        if ($workOrderId) {
            $workOrder = WorkOrder::find($workOrderId);
        }

        $workOrders = WorkOrder::orderBy('order_no')->get();

        return view('admin.production-anomalies.index', compact('anomalies', 'anomalyReasons', 'workOrder', 'workOrders'));
    }

    /**
     * Store a newly created production anomaly.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_order_id'     => 'required|exists:work_orders,id',
            'batch_id'          => 'nullable|exists:batches,id',
            'batch_step_id'     => 'nullable|exists:batch_steps,id',
            'anomaly_reason_id' => 'nullable|exists:anomaly_reasons,id',
            'product_name'      => 'nullable|string|max:255',
            'planned_qty'       => 'nullable|numeric|min:0',
            'actual_qty'        => 'nullable|numeric|min:0',
            'comment'           => 'nullable|string|max:2000',
        ]);

        $validated['created_by_id'] = auth()->id();
        $validated['status']        = ProductionAnomaly::STATUS_DRAFT;

        ProductionAnomaly::create($validated);

        return redirect()->back()
            ->with('success', 'Production anomaly recorded successfully.');
    }

    /**
     * Mark the anomaly as processed.
     */
    public function process(ProductionAnomaly $productionAnomaly)
    {
        if ($productionAnomaly->status === ProductionAnomaly::STATUS_PROCESSED) {
            return redirect()->back()
                ->with('error', 'This anomaly has already been processed.');
        }

        $productionAnomaly->update(['status' => ProductionAnomaly::STATUS_PROCESSED]);

        return redirect()->back()
            ->with('success', 'Production anomaly marked as processed.');
    }

    /**
     * Remove the specified production anomaly.
     */
    public function destroy(ProductionAnomaly $productionAnomaly)
    {
        if ($productionAnomaly->status === ProductionAnomaly::STATUS_PROCESSED) {
            return redirect()->route('admin.production-anomalies.index')
                ->with('error', 'Processed anomalies cannot be deleted.');
        }

        $productionAnomaly->delete();

        return redirect()->route('admin.production-anomalies.index')
            ->with('success', 'Production anomaly deleted successfully.');
    }
}
