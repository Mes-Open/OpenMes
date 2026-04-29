<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBomItemRequest;
use App\Http\Requests\Api\V1\UpdateBomItemRequest;
use App\Models\BomItem;
use App\Models\ProcessTemplate;
use App\Services\Material\BomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BomItemController extends Controller
{
    public function __construct(private BomService $bomService) {}

    public function index(ProcessTemplate $processTemplate): JsonResponse
    {
        $items = $this->bomService->listForTemplate($processTemplate);

        return response()->json(['data' => $items]);
    }

    public function store(StoreBomItemRequest $request, ProcessTemplate $processTemplate): JsonResponse
    {
        $item = $this->bomService->addItem($processTemplate, $request->validated());

        return response()->json([
            'message' => 'BOM item added',
            'data' => $item->load(['material.materialType', 'templateStep']),
        ], 201);
    }

    public function update(UpdateBomItemRequest $request, ProcessTemplate $processTemplate, BomItem $bomItem): JsonResponse
    {
        $item = $this->bomService->updateItem($bomItem, $request->validated());

        return response()->json([
            'message' => 'BOM item updated',
            'data' => $item,
        ]);
    }

    public function destroy(ProcessTemplate $processTemplate, BomItem $bomItem): JsonResponse
    {
        $this->bomService->removeItem($bomItem);

        return response()->json(['message' => 'BOM item removed']);
    }

    public function requirements(Request $request, ProcessTemplate $processTemplate): JsonResponse
    {
        $request->validate(['quantity' => 'required|numeric|gt:0']);

        $requirements = $this->bomService->calculateRequirements(
            $processTemplate,
            (float) $request->query('quantity'),
        );

        return response()->json(['data' => $requirements]);
    }
}
