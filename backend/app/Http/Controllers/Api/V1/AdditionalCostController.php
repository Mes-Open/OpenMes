<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdditionalCost;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdditionalCostController extends Controller
{
    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('viewAny', AdditionalCost::class);

        return response()->json([
            'data' => AdditionalCost::where('work_order_id', $workOrder->id)
                ->with(['costSource', 'createdBy'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('create', AdditionalCost::class);
        $data = $request->validate([
            'cost_source_id' => ['nullable', 'integer', 'exists:cost_sources,id'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);
        $data['work_order_id'] = $workOrder->id;
        $data['created_by_id'] = $request->user()->id;
        $cost = AdditionalCost::create($data);
        return response()->json([
            'message' => 'Cost added',
            'data' => $cost->load(['costSource', 'createdBy']),
        ], 201);
    }

    public function update(Request $request, AdditionalCost $additionalCost): JsonResponse
    {
        $this->authorize('update', $additionalCost);
        $data = $request->validate([
            'cost_source_id' => ['sometimes', 'nullable', 'integer', 'exists:cost_sources,id'],
            'description' => ['sometimes', 'required', 'string', 'max:500'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);
        $additionalCost->update($data);
        return response()->json(['message' => 'Updated', 'data' => $additionalCost->fresh(['costSource'])]);
    }

    public function destroy(AdditionalCost $additionalCost): JsonResponse
    {
        $this->authorize('delete', $additionalCost);
        $additionalCost->delete();
        return response()->json(['message' => 'Cost deleted']);
    }
}
