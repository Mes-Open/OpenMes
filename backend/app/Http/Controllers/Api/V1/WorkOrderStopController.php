<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StopWorkOrderRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderStop;
use App\Services\WorkOrder\WorkOrderStopService;
use Illuminate\Http\JsonResponse;

/**
 * Structured production stops on a work order (#182).
 *
 * Resuming lives on {@see WorkOrderController::resume()} because it is a work-order
 * status transition first and a stop closure second — the existing endpoint keeps its
 * URL and its behaviour for orders paused the simple way.
 */
class WorkOrderStopController extends Controller
{
    public function __construct(
        protected WorkOrderStopService $stops,
    ) {}

    /** Stop history, newest first, with the duration of each stop. */
    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        $stops = $workOrder->stops()
            ->with(['stoppedBy:id,name', 'resumedBy:id,name', 'batch:id,batch_number', 'appliedChangeRequest:id,code,status'])
            ->get()
            ->map(fn (WorkOrderStop $stop) => array_merge($stop->toArray(), [
                // Open stops report a running total, so a status page does not have to
                // compute it from the timestamp itself.
                'duration_minutes_current' => $stop->durationMinutes(),
            ]));

        return response()->json(['data' => $stops]);
    }

    public function store(StopWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);

        try {
            $stop = $this->stops->stop($workOrder, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Production stopped.',
            'data' => $stop->load(['stoppedBy:id,name', 'batch:id,batch_number']),
            'work_order' => $workOrder->fresh(),
        ], 201);
    }
}
