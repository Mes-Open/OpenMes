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

        if (!$allowOverproduction && ($totalTargetQty - $workOrder->planned_qty) > 0.001) {
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

    /**
     * Update a batch (only target_qty, only when PENDING).
     */
    public function update(Request $request, Batch $batch): JsonResponse
    {
        $this->authorize('update', $batch->workOrder);

        if ($batch->status !== Batch::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only PENDING batches can be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'target_qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        $batch->update(['target_qty' => $validated['target_qty']]);

        return response()->json([
            'message' => 'Batch updated',
            'data' => $batch->fresh(['steps']),
        ]);
    }

    /**
     * Cancel a batch.
     */
    public function cancel(Batch $batch): JsonResponse
    {
        $this->authorize('update', $batch->workOrder);

        if (in_array($batch->status, [Batch::STATUS_DONE, Batch::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'Batch is already in a terminal state.',
            ], 422);
        }

        $batch->update(['status' => Batch::STATUS_CANCELLED]);

        return response()->json([
            'message' => 'Batch cancelled',
            'data' => $batch->fresh(['steps']),
        ]);
    }

    /**
     * Delete a batch (only when PENDING and no started steps).
     */
    public function destroy(Batch $batch): JsonResponse
    {
        $this->authorize('delete', $batch->workOrder);

        if ($batch->status !== Batch::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only PENDING batches can be deleted.',
            ], 422);
        }

        $hasStarted = $batch->steps()
            ->whereNotIn('status', [\App\Models\BatchStep::STATUS_PENDING])
            ->exists();
        if ($hasStarted) {
            return response()->json([
                'message' => 'Cannot delete batch with started steps.',
            ], 422);
        }

        $batch->delete();
        return response()->json(['message' => 'Batch deleted']);
    }
}
