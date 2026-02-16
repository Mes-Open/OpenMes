<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BatchController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Get batches for a work order.
     *
     * @param WorkOrder $workOrder
     * @return JsonResponse
     */
    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        $batches = $workOrder->batches()
            ->with(['steps'])
            ->orderBy('batch_number')
            ->get();

        return response()->json([
            'data' => $batches,
        ]);
    }

    /**
     * Get a specific batch with steps.
     *
     * @param Batch $batch
     * @return JsonResponse
     */
    public function show(Batch $batch): JsonResponse
    {
        $this->authorize('view', $batch->workOrder);

        $batch->load([
            'workOrder.line',
            'workOrder.productType',
            'steps.startedBy',
            'steps.completedBy',
        ]);

        return response()->json([
            'data' => $batch,
        ]);
    }

    /**
     * Create a new batch for a work order.
     *
     * @param Request $request
     * @param WorkOrder $workOrder
     * @return JsonResponse
     */
    public function store(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);

        $validated = $request->validate([
            'target_qty' => 'required|numeric|min:0.01',
        ]);

        // Check if adding this batch would exceed planned qty
        $totalTargetQty = $workOrder->batches()->sum('target_qty') + $validated['target_qty'];
        $allowOverproduction = config('openmmes.allow_overproduction', false);

        if (!$allowOverproduction && bccomp($totalTargetQty, $workOrder->planned_qty, 2) > 0) {
            return response()->json([
                'message' => 'Total batch quantity would exceed planned quantity',
            ], 422);
        }

        $batch = $this->workOrderService->createBatch($workOrder, $validated['target_qty']);

        return response()->json([
            'message' => 'Batch created successfully',
            'data' => $batch->load('steps'),
        ], 201);
    }
}
