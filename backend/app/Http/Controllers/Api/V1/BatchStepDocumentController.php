<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchStepDocument\StoreBatchStepDocumentRequest;
use App\Models\BatchStep;
use App\Models\BatchStepDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shop-floor document control: attach mandatory/validatable documents to a step
 * (Supervisor/Admin) and validate them (operator). A step with an unvalidated
 * mandatory document cannot be completed - enforced in BatchService.
 */
class BatchStepDocumentController extends Controller
{
    /**
     * Attach a document to a step and mark it mandatory / validatable.
     * Supervisor/Admin only (gated by the request + route role middleware).
     */
    public function store(StoreBatchStepDocumentRequest $request, BatchStep $batchStep): JsonResponse
    {
        $data = [
            'batch_step_id' => $batchStep->id,
            'name' => $request->validated('name'),
            'reference' => $request->validated('reference'),
            // Default both flags to true: "mandatory and validatable" is the point.
            'is_mandatory' => $request->boolean('is_mandatory', true),
            'requires_validation' => $request->boolean('requires_validation', true),
            'uploaded_by_id' => $request->user()->id,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_path'] = $file->store('batch-step-documents');
            $data['original_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getClientMimeType();
            $data['file_size'] = $file->getSize();
        }

        $document = BatchStepDocument::create($data);

        return response()->json([
            'message' => __('Document attached.'),
            'data' => $document,
        ], 201);
    }

    /**
     * Validate a document (record who validated it and when). Idempotent.
     * Available to any authenticated production user (operator on the floor).
     */
    public function validateDocument(Request $request, BatchStepDocument $batchStepDocument): JsonResponse
    {
        // Authorize against the owning work order (same gate as the other
        // batch-step API actions) so a document can't be validated by id alone.
        $batchStepDocument->loadMissing('batchStep.batch.workOrder');
        $this->authorize('view', $batchStepDocument->batchStep->batch->workOrder);

        $batchStepDocument->markValidated($request->user());

        return response()->json([
            'message' => __('Document validated.'),
            'data' => $batchStepDocument->fresh('validatedBy'),
        ]);
    }
}
