<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\TemplateStep;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProcessTemplateManagementController extends Controller
{
    /**
     * Display process templates for a product type
     */
    public function index(ProductType $productType)
    {
        $templates = $productType->processTemplates()
            ->withCount('steps')
            ->orderBy('version', 'desc')
            ->get();

        return Inertia::render('admin/process-templates/Index', [
            'productType' => $productType->only('id', 'name'),
            'templates'   => $templates->map(fn($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'version'     => $t->version,
                'is_active'   => (bool) $t->is_active,
                'steps_count' => $t->steps_count,
                'created_at'  => $t->created_at->format('Y-m-d H:i'),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new process template
     */
    public function create(ProductType $productType)
    {
        return Inertia::render('admin/process-templates/Create', [
            'productType' => $productType->only('id', 'name'),
        ]);
    }

    /**
     * Store a newly created process template
     */
    public function store(Request $request, ProductType $productType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Get the next version number
        $latestVersion = $productType->processTemplates()->max('version') ?? 0;
        $validated['version'] = $latestVersion + 1;
        $validated['product_type_id'] = $productType->id;
        $validated['is_active'] = $request->boolean('is_active', true);

        $template = ProcessTemplate::create($validated);

        return redirect()->route('admin.product-types.process-templates.show', [$productType, $template])
            ->with('success', 'Process template created successfully. Now add production steps.');
    }

    /**
     * Display the specified process template
     */
    public function show(ProductType $productType, ProcessTemplate $processTemplate)
    {
        // Ensure template belongs to this product type
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $processTemplate->load([
            'steps' => fn($q) => $q->orderBy('step_number', 'asc'),
            'steps.workstation.line',
            'steps.processSegment',
            'photos.uploadedBy',
        ]);
        $workstations    = Workstation::active()->with('line')->orderBy('name')->get();
        $processSegments = \App\Models\ProcessSegment::query()
            ->active()
            ->orderBy('segment_type')
            ->orderBy('code')
            ->get();

        return Inertia::render('admin/process-templates/Show', [
            'productType'     => $processTemplate->productType->only('id', 'name'),
            'processTemplate' => [
                'id'         => $processTemplate->id,
                'name'       => $processTemplate->name,
                'version'    => $processTemplate->version,
                'is_active'  => (bool) $processTemplate->is_active,
                'steps'      => $processTemplate->steps->map(fn($s) => [
                    'id'                          => $s->id,
                    'step_number'                 => $s->step_number,
                    'name'                        => $s->name,
                    'instruction'                 => $s->instruction,
                    'estimated_duration_minutes'  => $s->estimated_duration_minutes,
                    'workstation_id'              => $s->workstation_id,
                    'process_segment_id'          => $s->process_segment_id,
                    'workstation'                 => $s->workstation ? [
                        'id'        => $s->workstation->id,
                        'name'      => $s->workstation->name,
                        'line_name' => $s->workstation->line?->name,
                    ] : null,
                    'process_segment'             => $s->processSegment ? [
                        'id'   => $s->processSegment->id,
                        'code' => $s->processSegment->code,
                    ] : null,
                ]),
                'photos'     => $processTemplate->photos->map(fn($p) => [
                    'id'            => $p->id,
                    'template_step_id' => $p->template_step_id,
                    'url'           => route('process-templates.photos.show', [$processTemplate, $p]),
                    'original_name' => $p->original_name,
                    'caption'       => $p->caption,
                    'width'         => $p->width,
                    'height'        => $p->height,
                    'file_size'     => $p->file_size_human ?? null,
                    'uploaded_by'   => $p->uploadedBy?->name,
                    'created_at'    => $p->created_at->format('Y-m-d H:i'),
                ]),
            ],
            'workstations'    => $workstations->map(fn($w) => [
                'id'        => $w->id,
                'name'      => $w->name,
                'line_name' => $w->line?->name,
            ]),
            'processSegments' => $processSegments->map(fn($s) => [
                'id'           => $s->id,
                'code'         => $s->code,
                'name'         => $s->name,
                'segment_type' => $s->segment_type,
                'instruction'  => $s->standard_instruction,
                'duration'     => $s->estimated_duration_minutes,
            ]),
        ]);
    }

    /**
     * Show the form for editing a process template
     */
    public function edit(ProductType $productType, ProcessTemplate $processTemplate)
    {
        // Ensure template belongs to this product type
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        return Inertia::render('admin/process-templates/Edit', [
            'productType'     => $productType->only('id', 'name'),
            'processTemplate' => [
                'id'        => $processTemplate->id,
                'name'      => $processTemplate->name,
                'version'   => $processTemplate->version,
                'is_active' => (bool) $processTemplate->is_active,
            ],
        ]);
    }

    /**
     * Update the specified process template
     */
    public function update(Request $request, ProductType $productType, ProcessTemplate $processTemplate)
    {
        // Ensure template belongs to this product type
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $processTemplate->update($validated);

        return redirect()->route('admin.product-types.process-templates.index', $productType)
            ->with('success', 'Process template updated successfully.');
    }

    /**
     * Remove the specified process template
     */
    public function destroy(ProductType $productType, ProcessTemplate $processTemplate)
    {
        // Ensure template belongs to this product type
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        // Check if template has steps
        if ($processTemplate->steps()->count() > 0) {
            return redirect()->route('admin.product-types.process-templates.index', $productType)
                ->with('error', 'Cannot delete process template with existing steps. Deactivate it instead.');
        }

        $processTemplate->delete();

        return redirect()->route('admin.product-types.process-templates.index', $productType)
            ->with('success', 'Process template deleted successfully.');
    }

    /**
     * Toggle process template active status
     */
    public function toggleActive(ProductType $productType, ProcessTemplate $processTemplate)
    {
        // Ensure template belongs to this product type
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $processTemplate->update(['is_active' => !$processTemplate->is_active]);

        $status = $processTemplate->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.product-types.process-templates.index', $productType)
            ->with('success', "Process template {$status} successfully.");
    }

    /**
     * Add a step to the process template
     */
    public function addStep(Request $request, ProductType $productType, ProcessTemplate $processTemplate)
    {
        // Ensure template belongs to this product type
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'instruction' => 'nullable|string',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'workstation_id' => 'nullable|exists:workstations,id',
            'process_segment_id' => 'nullable|exists:process_segments,id',
        ]);

        // Get the next step number
        $maxStepNumber = $processTemplate->steps()->max('step_number') ?? 0;
        $validated['step_number'] = $maxStepNumber + 1;
        $validated['process_template_id'] = $processTemplate->id;

        TemplateStep::create($validated);

        return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
            ->with('success', 'Step added successfully.');
    }

    /**
     * Update a step in the process template
     */
    public function updateStep(Request $request, ProductType $productType, ProcessTemplate $processTemplate, TemplateStep $step)
    {
        // Ensure template belongs to this product type and step belongs to template
        if ($processTemplate->product_type_id !== $productType->id || $step->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'instruction' => 'nullable|string',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'workstation_id' => 'nullable|exists:workstations,id',
            'process_segment_id' => 'nullable|exists:process_segments,id',
        ]);

        $step->update($validated);

        return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
            ->with('success', 'Step updated successfully.');
    }

    /**
     * Delete a step from the process template
     */
    public function deleteStep(ProductType $productType, ProcessTemplate $processTemplate, TemplateStep $step)
    {
        // Ensure template belongs to this product type and step belongs to template
        if ($processTemplate->product_type_id !== $productType->id || $step->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        $stepNumber = $step->step_number;
        $step->delete();

        // Renumber remaining steps
        DB::table('template_steps')
            ->where('process_template_id', $processTemplate->id)
            ->where('step_number', '>', $stepNumber)
            ->decrement('step_number');

        return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
            ->with('success', 'Step deleted successfully.');
    }

    /**
     * Reorder steps via drag and drop (expects JSON body: {order: [id, id, ...]})
     */
    public function reorderSteps(Request $request, ProductType $productType, ProcessTemplate $processTemplate)
    {
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $validated = $request->validate([
            'order'   => 'required|array|min:1',
            'order.*' => 'integer',
        ]);

        $stepIds = $validated['order'];

        // Verify every submitted ID belongs to this template
        $validCount = DB::table('template_steps')
            ->where('process_template_id', $processTemplate->id)
            ->whereIn('id', $stepIds)
            ->count();

        if ($validCount !== count($stepIds)) {
            return response()->json(['error' => 'Invalid step IDs'], 422);
        }

        // Use large offset first to avoid unique(process_template_id, step_number) violations
        DB::transaction(function () use ($stepIds) {
            $offset = 10000;
            foreach ($stepIds as $i => $id) {
                DB::table('template_steps')->where('id', $id)->update(['step_number' => $offset + $i + 1]);
            }
            foreach ($stepIds as $i => $id) {
                DB::table('template_steps')->where('id', $id)->update(['step_number' => $i + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Move a step up in the order
     */
    public function moveStepUp(ProductType $productType, ProcessTemplate $processTemplate, TemplateStep $step)
    {
        // Ensure template belongs to this product type and step belongs to template
        if ($processTemplate->product_type_id !== $productType->id || $step->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        if ($step->step_number <= 1) {
            return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
                ->with('error', 'Step is already first.');
        }

        // Swap with previous step
        $previousStep = $processTemplate->steps()
            ->where('step_number', $step->step_number - 1)
            ->first();

        if ($previousStep) {
            $origStep     = $step->step_number;
            $origPrevious = $previousStep->step_number;
            DB::table('template_steps')->where('id', $step->id)->update(['step_number' => -1]);
            DB::table('template_steps')->where('id', $previousStep->id)->update(['step_number' => $origStep]);
            DB::table('template_steps')->where('id', $step->id)->update(['step_number' => $origPrevious]);
        }

        return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
            ->with('success', 'Step moved up successfully.');
    }

    /**
     * Move a step down in the order
     */
    public function moveStepDown(ProductType $productType, ProcessTemplate $processTemplate, TemplateStep $step)
    {
        // Ensure template belongs to this product type and step belongs to template
        if ($processTemplate->product_type_id !== $productType->id || $step->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        $maxStepNumber = $processTemplate->steps()->max('step_number');
        if ($step->step_number >= $maxStepNumber) {
            return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
                ->with('error', 'Step is already last.');
        }

        // Swap with next step
        $nextStep = $processTemplate->steps()
            ->where('step_number', $step->step_number + 1)
            ->first();

        if ($nextStep) {
            $origStep     = $step->step_number;
            $origNext     = $nextStep->step_number;
            DB::table('template_steps')->where('id', $step->id)->update(['step_number' => -1]);
            DB::table('template_steps')->where('id', $nextStep->id)->update(['step_number' => $origStep]);
            DB::table('template_steps')->where('id', $step->id)->update(['step_number' => $origNext]);
        }

        return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
            ->with('success', 'Step moved down successfully.');
    }
}
