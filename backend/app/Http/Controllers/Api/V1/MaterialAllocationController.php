<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordConsumptionRequest;
use App\Http\Requests\Api\V1\ReturnAllocationRequest;
use App\Models\MaterialAllocation;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Http\JsonResponse;

/**
 * Work-order material reconciliation (#99): declare actual (partial) consumption
 * and return unused quantity to stock. Both funnel through
 * MaterialAllocationService so stock, reservation and lot accounting stay in sync.
 */
class MaterialAllocationController extends Controller
{
    public function __construct(
        protected MaterialAllocationService $allocations,
    ) {}

    public function consume(RecordConsumptionRequest $request, MaterialAllocation $allocation): JsonResponse
    {
        try {
            $updated = $this->allocations->recordConsumption(
                $allocation,
                (float) $request->validated('consumed_qty'),
                (float) ($request->validated('scrap_qty') ?? 0),
                $request->validated('notes'),
            );

            return response()->json([
                'message' => 'Consumption recorded',
                'data' => $updated,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['consumed_qty' => [$e->getMessage()]],
            ], 422);
        }
    }

    public function return(ReturnAllocationRequest $request, MaterialAllocation $allocation): JsonResponse
    {
        try {
            $updated = $this->allocations->returnQuantity(
                $allocation,
                (float) $request->validated('qty'),
                $request->user(),
                $request->validated('reason'),
            );

            return response()->json([
                'message' => 'Material returned to stock',
                'data' => $updated,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['qty' => [$e->getMessage()]],
            ], 422);
        }
    }
}
