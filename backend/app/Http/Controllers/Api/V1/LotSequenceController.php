<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLotSequenceRequest;
use App\Http\Requests\Api\V1\UpdateLotSequenceRequest;
use App\Models\LotSequence;
use App\Services\Lot\LotService;
use Illuminate\Http\JsonResponse;

class LotSequenceController extends Controller
{
    public function index(): JsonResponse
    {
        $sequences = LotSequence::with('productType')->orderBy('name')->get();

        return response()->json(['data' => $sequences]);
    }

    public function show(LotSequence $lotSequence): JsonResponse
    {
        $lotSequence->load('productType');

        return response()->json(['data' => $lotSequence]);
    }

    public function store(StoreLotSequenceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['year_prefix'] = $data['year_prefix'] ?? true;
        $data['pad_size'] = $data['pad_size'] ?? 4;

        $sequence = LotSequence::create($data);

        return response()->json([
            'message' => 'LOT sequence created',
            'data' => $sequence->load('productType'),
        ], 201);
    }

    public function update(UpdateLotSequenceRequest $request, LotSequence $lotSequence): JsonResponse
    {
        $lotSequence->update($request->validated());

        return response()->json([
            'message' => 'LOT sequence updated',
            'data' => $lotSequence->fresh('productType'),
        ]);
    }

    public function destroy(LotSequence $lotSequence): JsonResponse
    {
        $lotSequence->delete();

        return response()->json(['message' => 'LOT sequence deleted']);
    }

    public function preview(LotService $lotService, ?int $productTypeId = null): JsonResponse
    {
        $productType = $productTypeId ? \App\Models\ProductType::find($productTypeId) : null;
        $preview = $lotService->previewNext($productType);

        return response()->json([
            'data' => ['next_lot' => $preview],
        ]);
    }
}
