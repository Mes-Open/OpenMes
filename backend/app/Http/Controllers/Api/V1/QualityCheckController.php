<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QcTemplateRequest;
use App\Http\Requests\Api\V1\StoreQualityCheckRequest;
use App\Models\Batch;
use App\Models\QualityCheckTemplate;
use App\Services\Production\QualityCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityCheckController extends Controller
{
    public function __construct(private QualityCheckService $service) {}

    public function index(Batch $batch): JsonResponse
    {
        $checks = $batch->qualityChecks()->with(['samples', 'checkedBy'])->get();

        return response()->json(['data' => $checks]);
    }

    /**
     * Record an in-process check. The verdict is decided on the server from the
     * QC template (see QualityCheckService::performCheck); a sample that can't be
     * judged comes back as a 422 field error.
     */
    public function store(StoreQualityCheckRequest $request, Batch $batch): JsonResponse
    {
        $validated = $request->validated();

        // Optional pallet link (#106): the pallet must belong to the batch's work order.
        $pallet = null;
        if (! empty($validated['pallet_id'])) {
            $pallet = \App\Models\Pallet::find($validated['pallet_id']);
            if ($pallet && $pallet->work_order_id !== $batch->work_order_id) {
                return response()->json(['message' => 'The pallet belongs to a different work order.'], 422);
            }
        }

        $check = $this->service->performCheck(
            $batch,
            $request->user(),
            $request->samples(),
            $validated['production_quantity'] ?? null,
            $request->template(),
            $validated['notes'] ?? null,
            $pallet,
        );

        return response()->json([
            'message' => 'Quality check recorded',
            'data' => $check,
        ], 201);
    }

    public function status(Batch $batch): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getCheckStatus($batch, QualityCheckTemplate::forBatch($batch)),
        ]);
    }

    // QC Template CRUD (Admin)

    public function templateIndex(int $processTemplateId): JsonResponse
    {
        $templates = QualityCheckTemplate::where('process_template_id', $processTemplateId)->get();

        return response()->json(['data' => $templates]);
    }

    public function templateStore(QcTemplateRequest $request, int $processTemplateId): JsonResponse
    {
        $validated = $request->validated();

        $validated['process_template_id'] = $processTemplateId;
        // These columns are NOT NULL DEFAULT 3; a null in the payload would be
        // inserted explicitly and trip the constraint, so restore the defaults.
        $validated['min_checks_per_batch'] ??= 3;
        $validated['samples_per_check'] ??= 3;

        $template = QualityCheckTemplate::create($validated);

        return response()->json([
            'message' => 'QC template created',
            'data' => $template,
        ], 201);
    }

    public function templateUpdate(QcTemplateRequest $request, QualityCheckTemplate $qualityCheckTemplate): JsonResponse
    {
        $validated = $request->validated();

        // NOT NULL DEFAULT 3 columns — preserve existing values if the payload
        // sends an explicit null rather than omitting them.
        $validated['min_checks_per_batch'] ??= $qualityCheckTemplate->min_checks_per_batch;
        $validated['samples_per_check'] ??= $qualityCheckTemplate->samples_per_check;

        $qualityCheckTemplate->update($validated);

        return response()->json([
            'message' => 'QC template updated',
            'data' => $qualityCheckTemplate->fresh(),
        ]);
    }

    public function templateDestroy(QualityCheckTemplate $qualityCheckTemplate): JsonResponse
    {
        $qualityCheckTemplate->delete();

        return response()->json(['message' => 'QC template deleted']);
    }
}
