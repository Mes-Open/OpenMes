<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

    public function store(Request $request, Batch $batch): JsonResponse
    {
        $validated = $request->validate([
            'production_quantity' => 'nullable|numeric|min:0',
            'quality_check_template_id' => 'nullable|exists:quality_check_templates,id',
            'notes' => 'nullable|string',
            'samples' => 'required|array|min:1',
            'samples.*.sample_number' => 'required|integer|min:1',
            'samples.*.parameter_name' => 'required|string|max:100',
            'samples.*.parameter_type' => 'required|string|in:measurement,pass_fail',
            'samples.*.value_numeric' => 'nullable|numeric',
            'samples.*.value_boolean' => 'nullable|boolean',
            'samples.*.is_passed' => 'nullable|boolean',
        ]);

        $template = isset($validated['quality_check_template_id'])
            ? QualityCheckTemplate::find($validated['quality_check_template_id'])
            : null;

        $check = $this->service->performCheck(
            $batch,
            $request->user(),
            $validated['samples'],
            $validated['production_quantity'] ?? null,
            $template,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Quality check recorded',
            'data' => $check,
        ], 201);
    }

    public function status(Batch $batch): JsonResponse
    {
        $template = null;
        $workOrder = $batch->workOrder;
        if ($workOrder->process_snapshot) {
            $templateId = $workOrder->process_snapshot['template_id'] ?? null;
            if ($templateId) {
                $template = QualityCheckTemplate::where('process_template_id', $templateId)->first();
            }
        }

        return response()->json([
            'data' => $this->service->getCheckStatus($batch, $template),
        ]);
    }

    // QC Template CRUD (Admin)

    public function templateIndex(int $processTemplateId): JsonResponse
    {
        $templates = QualityCheckTemplate::where('process_template_id', $processTemplateId)->get();

        return response()->json(['data' => $templates]);
    }

    public function templateStore(Request $request, int $processTemplateId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'min_checks_per_batch' => 'nullable|integer|min:1',
            'min_checks_per_day' => 'nullable|integer|min:1',
            'samples_per_check' => 'nullable|integer|min:1',
            'parameters' => 'required|array|min:1',
            'parameters.*.name' => 'required|string|max:100',
            'parameters.*.type' => 'required|string|in:measurement,pass_fail',
            'parameters.*.unit' => 'nullable|string|max:20',
            'parameters.*.min' => 'nullable|numeric',
            'parameters.*.max' => 'nullable|numeric',
        ]);

        $validated['process_template_id'] = $processTemplateId;

        $template = QualityCheckTemplate::create($validated);

        return response()->json([
            'message' => 'QC template created',
            'data' => $template,
        ], 201);
    }

    public function templateUpdate(Request $request, QualityCheckTemplate $qualityCheckTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'min_checks_per_batch' => 'nullable|integer|min:1',
            'min_checks_per_day' => 'nullable|integer|min:1',
            'samples_per_check' => 'nullable|integer|min:1',
            'parameters' => 'sometimes|array|min:1',
            'parameters.*.name' => 'required|string|max:100',
            'parameters.*.type' => 'required|string|in:measurement,pass_fail',
            'parameters.*.unit' => 'nullable|string|max:20',
            'parameters.*.min' => 'nullable|numeric',
            'parameters.*.max' => 'nullable|numeric',
        ]);

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
