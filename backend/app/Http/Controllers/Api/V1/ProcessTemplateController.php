<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProcessTemplateRequest;
use App\Http\Requests\Api\V1\StoreTemplateStepRequest;
use App\Http\Requests\Api\V1\UpdateProcessTemplateRequest;
use App\Http\Requests\Api\V1\UpdateTemplateStepRequest;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessTemplateController extends Controller
{
    public function index(Request $request, ProductType $productType): JsonResponse
    {
        $query = $productType->processTemplates()->with('steps.workstation');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->orderByDesc('version')->get(),
        ]);
    }

    public function show(ProcessTemplate $processTemplate): JsonResponse
    {
        $this->authorize('view', $processTemplate);
        $processTemplate->load(['productType', 'steps.workstation']);
        return response()->json(['data' => $processTemplate]);
    }

    public function store(StoreProcessTemplateRequest $request, ProductType $productType): JsonResponse
    {
        $this->authorize('create', ProcessTemplate::class);

        $data = $request->validated();
        $data['product_type_id'] = $productType->id;
        $data['is_active'] = $data['is_active'] ?? true;

        // Auto-bump version if not provided
        if (!isset($data['version'])) {
            $maxVersion = $productType->processTemplates()->max('version') ?? 0;
            $data['version'] = $maxVersion + 1;
        }

        $template = ProcessTemplate::create($data);

        return response()->json([
            'message' => 'Process template created',
            'data' => $template->load('steps'),
        ], 201);
    }

    public function update(UpdateProcessTemplateRequest $request, ProcessTemplate $processTemplate): JsonResponse
    {
        $this->authorize('update', $processTemplate);
        $processTemplate->update($request->validated());
        return response()->json([
            'message' => 'Process template updated',
            'data' => $processTemplate->fresh(['steps']),
        ]);
    }

    public function destroy(ProcessTemplate $processTemplate): JsonResponse
    {
        $this->authorize('delete', $processTemplate);

        // Optional: prevent deletion if used by work orders
        // (work orders snapshot the template so this is safe, but warn anyway)
        $processTemplate->delete();
        return response()->json(['message' => 'Process template deleted']);
    }

    public function toggleActive(ProcessTemplate $processTemplate): JsonResponse
    {
        $this->authorize('update', $processTemplate);
        $processTemplate->update(['is_active' => !$processTemplate->is_active]);
        return response()->json([
            'message' => $processTemplate->is_active ? 'Activated' : 'Deactivated',
            'data' => $processTemplate,
        ]);
    }

    // ── Steps ────────────────────────────────────────────────────────────────

    public function addStep(StoreTemplateStepRequest $request, ProcessTemplate $processTemplate): JsonResponse
    {
        $this->authorize('update', $processTemplate);

        $data = $request->validated();
        if (!isset($data['step_number'])) {
            $data['step_number'] = ($processTemplate->steps()->max('step_number') ?? 0) + 1;
        }
        $data['process_template_id'] = $processTemplate->id;

        $step = TemplateStep::create($data);

        return response()->json([
            'message' => 'Step added',
            'data' => $step->load('workstation'),
        ], 201);
    }

    public function updateStep(UpdateTemplateStepRequest $request, TemplateStep $templateStep): JsonResponse
    {
        $this->authorize('update', $templateStep->processTemplate);
        $templateStep->update($request->validated());
        return response()->json([
            'message' => 'Step updated',
            'data' => $templateStep->fresh(['workstation']),
        ]);
    }

    public function destroyStep(TemplateStep $templateStep): JsonResponse
    {
        $this->authorize('update', $templateStep->processTemplate);
        $templateStep->delete();
        return response()->json(['message' => 'Step deleted']);
    }

    public function reorderSteps(Request $request, ProcessTemplate $processTemplate): JsonResponse
    {
        $this->authorize('update', $processTemplate);

        $validated = $request->validate([
            'step_ids' => ['required', 'array', 'min:1'],
            'step_ids.*' => ['integer', 'exists:template_steps,id'],
        ]);

        $stepIds = $validated['step_ids'];

        // Verify all steps belong to this template
        $count = $processTemplate->steps()->whereIn('id', $stepIds)->count();
        if ($count !== count($stepIds)) {
            return response()->json([
                'message' => 'All step IDs must belong to this template',
            ], 422);
        }

        DB::transaction(function () use ($stepIds, $processTemplate) {
            // Use temporary high numbers first to avoid uniqueness collisions
            foreach ($stepIds as $i => $id) {
                TemplateStep::where('id', $id)->update(['step_number' => 10000 + $i]);
            }
            foreach ($stepIds as $i => $id) {
                TemplateStep::where('id', $id)->update(['step_number' => $i + 1]);
            }
        });

        return response()->json([
            'message' => 'Steps reordered',
            'data' => $processTemplate->fresh(['steps.workstation']),
        ]);
    }
}
