<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\Production\PackagingChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackagingChecklistController extends Controller
{
    public function __construct(private PackagingChecklistService $service) {}

    public function show(Batch $batch): JsonResponse
    {
        $checklist = $batch->packagingChecklist;

        return response()->json([
            'data' => $checklist,
            'is_complete' => $this->service->isComplete($batch),
        ]);
    }

    public function store(Request $request, Batch $batch): JsonResponse
    {
        $validated = $request->validate([
            'udi_readable' => 'required|boolean',
            'packaging_condition' => 'required|boolean',
            'labels_readable' => 'required|boolean',
            'label_matches_product' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $checklist = $this->service->submit($batch, $request->user(), $validated);

            return response()->json([
                'message' => 'Packaging checklist submitted',
                'data' => $checklist->fresh(),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
