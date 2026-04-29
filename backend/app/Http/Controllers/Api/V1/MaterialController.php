<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportMaterialsRequest;
use App\Http\Requests\Api\V1\StoreMaterialRequest;
use App\Http\Requests\Api\V1\UpdateMaterialRequest;
use App\Models\Material;
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
        $material->load(['materialType', 'sources.integrationConfig']);

        return response()->json(['data' => $material]);
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
