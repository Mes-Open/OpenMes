<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductionAnomaly;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionAnomalyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductionAnomaly::query()->with(['anomalyReason', 'createdBy', 'workOrder']);
        if ($woId = $request->query('work_order_id')) {
            $query->where('work_order_id', $woId);
        }
        if ($lineId = $request->query('line_id')) {
            $query->whereHas('workOrder', fn($q) => $q->where('line_id', $lineId));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) $query->where('created_at', '>=', $from);
        if ($to = $request->query('to')) $query->where('created_at', '<=', $to);

        $perPage = max(1, min((int) $request->query('per_page', 30), 100));
        $page = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(ProductionAnomaly $productionAnomaly): JsonResponse
    {
        $this->authorize('view', $productionAnomaly);
        $productionAnomaly->load(['anomalyReason', 'createdBy', 'workOrder', 'batch', 'batchStep']);
        return response()->json(['data' => $productionAnomaly]);
    }

    public function store(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('create', ProductionAnomaly::class);
        $data = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'batch_step_id' => ['nullable', 'integer', 'exists:batch_steps,id'],
            'anomaly_reason_id' => ['required', 'integer', 'exists:anomaly_reasons,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'planned_qty' => ['required', 'numeric', 'min:0'],
            'actual_qty' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([ProductionAnomaly::STATUS_DRAFT, ProductionAnomaly::STATUS_PROCESSED])],
        ]);
        $data['work_order_id'] = $workOrder->id;
        $data['created_by_id'] = $request->user()->id;
        $data['status'] = $data['status'] ?? ProductionAnomaly::STATUS_DRAFT;
        // product_name is NOT NULL — default from work order's product type
        if (empty($data['product_name'])) {
            $workOrder->loadMissing('productType');
            $data['product_name'] = $workOrder->productType?->name ?? $workOrder->order_no;
        }

        $anomaly = ProductionAnomaly::create($data);

        return response()->json([
            'message' => 'Anomaly recorded',
            'data' => $anomaly->load(['anomalyReason', 'createdBy']),
        ], 201);
    }

    public function update(Request $request, ProductionAnomaly $productionAnomaly): JsonResponse
    {
        $this->authorize('update', $productionAnomaly);
        $data = $request->validate([
            'anomaly_reason_id' => ['sometimes', 'integer', 'exists:anomaly_reasons,id'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'planned_qty' => ['sometimes', 'numeric', 'min:0'],
            'actual_qty' => ['sometimes', 'numeric', 'min:0'],
            'comment' => ['sometimes', 'nullable', 'string'],
        ]);
        $productionAnomaly->update($data);
        return response()->json(['message' => 'Updated', 'data' => $productionAnomaly->fresh(['anomalyReason'])]);
    }

    public function destroy(ProductionAnomaly $productionAnomaly): JsonResponse
    {
        $this->authorize('delete', $productionAnomaly);
        $productionAnomaly->delete();
        return response()->json(['message' => 'Anomaly deleted']);
    }

    public function process(ProductionAnomaly $productionAnomaly): JsonResponse
    {
        $this->authorize('process', $productionAnomaly);
        if ($productionAnomaly->status === ProductionAnomaly::STATUS_PROCESSED) {
            return response()->json(['message' => 'Already processed.'], 422);
        }
        $productionAnomaly->update(['status' => ProductionAnomaly::STATUS_PROCESSED]);
        return response()->json(['message' => 'Processed', 'data' => $productionAnomaly->fresh()]);
    }
}
