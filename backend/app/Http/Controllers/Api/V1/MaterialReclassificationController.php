<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReclassifyClassRequest;
use App\Http\Requests\Api\V1\ReclassifyLotStatusRequest;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Services\Material\MaterialReclassificationService;
use Illuminate\Http\JsonResponse;

/**
 * Material reclassification (#99): regrade a quantity between material classes,
 * or change a lot's status. Route-gated to Supervisor|Admin.
 */
class MaterialReclassificationController extends Controller
{
    public function __construct(
        protected MaterialReclassificationService $reclassifications,
    ) {}

    public function class(ReclassifyClassRequest $request): JsonResponse
    {
        try {
            $source = Material::findOrFail($request->validated('source_material_id'));
            $target = Material::findOrFail($request->validated('target_material_id'));
            $lot = $request->validated('source_lot_id')
                ? MaterialLot::findOrFail($request->validated('source_lot_id'))
                : null;

            $record = $this->reclassifications->reclassifyClass(
                $source,
                $target,
                (float) $request->validated('qty'),
                $request->user(),
                $lot,
                $request->validated('reason'),
            );

            return response()->json([
                'message' => 'Material reclassified',
                'data' => $record,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['qty' => [$e->getMessage()]],
            ], 422);
        }
    }

    public function status(ReclassifyLotStatusRequest $request, MaterialLot $materialLot): JsonResponse
    {
        try {
            $record = $this->reclassifications->reclassifyStatus(
                $materialLot,
                $request->validated('to_status'),
                $request->user(),
                $request->validated('reason'),
            );

            return response()->json([
                'message' => 'Lot status changed',
                'data' => $record,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['to_status' => [$e->getMessage()]],
            ], 422);
        }
    }
}
