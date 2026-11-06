<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Api\V1\Erp\Concerns\ResolvesErpWorkOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Erp\CancelErpWorkOrderRequest;
use App\Http\Resources\Erp\ErpChangeRequestPresenter;
use App\Services\Erp\ErpWorkOrderControlService;
use Illuminate\Http\JsonResponse;

/**
 * ERP → OpenMES: cancel a work order.
 *
 * Before production starts the order is cancelled at once (200, `cancelled`). Once
 * it is in production the ERP cannot cancel it on its own: a cancellation change
 * request goes to a manager (202, `change_requested`), and the ERP follows its fate
 * through the change-request feed. A finished order answers 409 with its status.
 *
 * Requires the `erp:orders:import` scope; rate limited via the `erp-import` limiter.
 */
class WorkOrderCancelController extends Controller
{
    use ResolvesErpWorkOrder;

    public function store(
        CancelErpWorkOrderRequest $request,
        string $workOrder,
        ErpWorkOrderControlService $control,
    ): JsonResponse {
        $order = $this->resolveWorkOrder($workOrder, $request->lookup());

        try {
            $outcome = $control->cancel($order, $request->attributes->get('api_key'), $request->reason());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'status' => $order->fresh()?->status], 409);
        }

        $workOrderData = [
            'id' => $outcome['work_order']->id,
            'order_no' => $outcome['work_order']->order_no,
            'status' => $outcome['status'],
        ];

        if ($outcome['result'] === 'conflict') {
            return response()->json([
                'message' => __('Work order is already :status.', ['status' => $outcome['status']]),
                'status' => $outcome['status'],
                'work_order' => $workOrderData,
            ], 409);
        }

        if ($outcome['result'] === 'cancelled') {
            return response()->json(['data' => [
                'result' => 'cancelled',
                'work_order' => $workOrderData,
            ]]);
        }

        $changeRequest = $outcome['change_request'];
        $changeRequest->setRelation('workOrder', $outcome['work_order']);

        return response()->json(['data' => [
            'result' => 'change_requested',
            'change_request_id' => $changeRequest->id,
            'change_request' => (new ErpChangeRequestPresenter([$changeRequest]))->present($changeRequest),
            'work_order' => $workOrderData,
        ]], 202);
    }
}
