<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkOrderController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Get list of work orders (filtered by user's assigned lines).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get filters from request
        $filters = $request->only(['status', 'line_id']);

        $workOrders = $this->workOrderService->getWorkOrdersForUser($user, $filters);

        return response()->json([
            'data' => $workOrders,
        ]);
    }

    /**
     * Get a specific work order.
     *
     * @param WorkOrder $workOrder
     * @return JsonResponse
     */
    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        $workOrder->load([
            'line',
            'productType',
            'batches.steps.startedBy',
            'batches.steps.completedBy',
            'issues.issueType',
        ]);

        return response()->json([
            'data' => $workOrder,
        ]);
    }

    /**
     * Create a new work order.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);

        $validated = $request->validate([
            'order_no' => 'required|string|max:100|unique:work_orders,order_no',
            'line_id' => 'nullable|exists:lines,id',
            'product_type_id' => 'nullable|exists:product_types,id',
            'planned_qty' => 'required|numeric|min:0.01',
            'priority' => 'nullable|integer',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'extra_data' => 'nullable|array',
        ]);

        $workOrder = $this->workOrderService->createWorkOrder($validated);

        return response()->json([
            'message' => 'Work order created successfully',
            'data' => $workOrder->load(['line', 'productType']),
        ], 201);
    }

    /**
     * Update a work order.
     *
     * @param Request $request
     * @param WorkOrder $workOrder
     * @return JsonResponse
     */
    public function update(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);

        $validated = $request->validate([
            'planned_qty' => 'nullable|numeric|min:0.01',
            'priority' => 'nullable|integer',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $workOrder = $this->workOrderService->updateWorkOrder($workOrder, $validated);

        return response()->json([
            'message' => 'Work order updated successfully',
            'data' => $workOrder->load(['line', 'productType']),
        ]);
    }

    /**
     * Delete a work order.
     *
     * @param WorkOrder $workOrder
     * @return JsonResponse
     */
    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('delete', $workOrder);

        // Only allow deletion of pending work orders
        if ($workOrder->status !== WorkOrder::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending work orders can be deleted',
            ], 422);
        }

        $workOrder->delete();

        return response()->json([
            'message' => 'Work order deleted successfully',
        ]);
    }

    // ── Status transitions ──────────────────────────────────────────────────

    public function accept(WorkOrder $workOrder): JsonResponse
    {
        return $this->transition($workOrder, WorkOrder::STATUS_ACCEPTED, [WorkOrder::STATUS_PENDING],
            'Only PENDING work orders can be accepted.');
    }

    public function reject(WorkOrder $workOrder): JsonResponse
    {
        return $this->transition($workOrder, WorkOrder::STATUS_REJECTED,
            [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED],
            'Only PENDING or ACCEPTED work orders can be rejected.');
    }

    public function cancel(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES, true)) {
            return response()->json([
                'message' => 'Cannot cancel a work order that is already in a terminal state.',
            ], 422);
        }
        $workOrder->update(['status' => WorkOrder::STATUS_CANCELLED]);
        return response()->json([
            'message' => 'Work order cancelled',
            'data' => $workOrder->fresh(['line', 'productType']),
        ]);
    }

    public function pause(WorkOrder $workOrder): JsonResponse
    {
        return $this->transition($workOrder, WorkOrder::STATUS_PAUSED, [WorkOrder::STATUS_IN_PROGRESS],
            'Only IN_PROGRESS work orders can be paused.');
    }

    public function resume(WorkOrder $workOrder): JsonResponse
    {
        return $this->transition($workOrder, WorkOrder::STATUS_IN_PROGRESS,
            [WorkOrder::STATUS_PAUSED, WorkOrder::STATUS_BLOCKED],
            'Only PAUSED or BLOCKED work orders can be resumed.');
    }

    public function reopen(WorkOrder $workOrder): JsonResponse
    {
        return $this->transition($workOrder, WorkOrder::STATUS_IN_PROGRESS,
            WorkOrder::TERMINAL_STATUSES,
            'Only terminal work orders (DONE/REJECTED/CANCELLED) can be reopened.');
    }

    public function complete(WorkOrder $workOrder): JsonResponse
    {
        return $this->transition($workOrder, WorkOrder::STATUS_DONE, [WorkOrder::STATUS_IN_PROGRESS],
            'Only IN_PROGRESS work orders can be completed.');
    }

    private function transition(WorkOrder $workOrder, string $target, array $allowedFrom, string $errorMessage): JsonResponse
    {
        $this->authorize('update', $workOrder);

        if (!in_array($workOrder->status, $allowedFrom, true)) {
            return response()->json(['message' => $errorMessage], 422);
        }

        $workOrder->update(['status' => $target]);

        return response()->json([
            'message' => "Work order status set to {$target}",
            'data' => $workOrder->fresh(['line', 'productType']),
        ]);
    }
}
