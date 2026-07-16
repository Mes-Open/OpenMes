<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportMaterialsRequest;
use App\Http\Requests\Api\V1\StoreMaterialRequest;
use App\Http\Requests\Api\V1\UpdateMaterialRequest;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockMovement;
use App\Services\Material\MaterialService;
use App\Services\Material\MaterialSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(
        private MaterialService $materialService,
        private MaterialSyncService $syncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $materials = $this->materialService->list($request->only([
            'material_type_id', 'is_active', 'search', 'external_system',
        ]));

        return response()->json(['data' => $materials]);
    }

    public function show(Material $material): JsonResponse
    {
        $material->load([
            'materialType',
            'sources.integrationConfig',
            'bomItems.processTemplate.productType',
        ]);
        $material->append('available_quantity');

        $lots = MaterialLot::where('material_id', $material->id)
            ->orderByRaw("CASE WHEN status = 'available' THEN 0 ELSE 1 END")
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->limit(20)
            ->get()
            ->map(fn ($lot) => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'supplier_lot_no' => $lot->supplier_lot_no,
                'quantity_received' => $lot->quantity_received,
                'quantity_available' => $lot->quantity_available,
                'expiry_date' => $lot->expiry_date?->toDateString(),
                'status' => $lot->status,
                'is_expired' => $lot->isExpired(),
            ]);

        $recentMovements = StockMovement::forMaterial($material->id)
            ->limit(15)
            ->get()
            ->map(fn ($mv) => [
                'id' => $mv->id,
                'performed_at' => $mv->performed_at?->toIso8601String(),
                'movement_type' => $mv->movement_type,
                'quantity' => $mv->quantity,
                'balance_after' => $mv->balance_after,
                'source_type' => $mv->source_type,
                'source_id' => $mv->source_id,
                'reason' => $mv->reason,
                'performed_by' => $mv->performedBy ? ['name' => $mv->performedBy->name] : null,
            ]);

        $bomUsage = $material->bomItems->map(fn ($item) => [
            'id' => $item->id,
            'quantity_per_unit' => $item->quantity_per_unit,
            'scrap_percentage' => $item->scrap_percentage,
            'process_template' => $item->processTemplate ? [
                'name' => $item->processTemplate->name,
                'product_type' => $item->processTemplate->productType
                    ? ['name' => $item->processTemplate->productType->name]
                    : null,
            ] : null,
        ])->values();

        $data = $material->toArray();
        $data['reserved_quantity'] = $material->reserved_quantity ?? 0;
        $data['lots'] = $lots;
        $data['recent_movements'] = $recentMovements;
        $data['bom_usage'] = $bomUsage;

        return response()->json(['data' => $data]);
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $material = $this->materialService->create($request->validated());

        return response()->json([
            'message' => 'Material created',
            'data' => $material->load('materialType'),
        ], 201);
    }

    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        $material = $this->materialService->update($material, $request->validated());

        return response()->json([
            'message' => 'Material updated',
            'data' => $material,
        ]);
    }

    public function destroy(Material $material): JsonResponse
    {
        if ($material->bomItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete material used in BOM. Deactivate it instead.',
            ], 422);
        }

        $this->materialService->delete($material);

        return response()->json(['message' => 'Material deleted']);
    }

    public function import(ImportMaterialsRequest $request): JsonResponse
    {
        $result = $this->syncService->importFromExternalSystem(
            $request->validated('source_system'),
            $request->validated('materials'),
        );

        return response()->json([
            'message' => "Import complete: {$result['created']} created, {$result['updated']} updated",
            'data' => $result,
        ]);
    }
}
