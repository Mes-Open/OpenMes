<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\TemplateStep;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return view('admin.process-templates.index', compact('productType', 'templates'));
    }

    /**
     * Show the form for creating a new process template
     */
    public function create(ProductType $productType)
    {
        return view('admin.process-templates.create', compact('productType'));
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
            'steps.workstation',
        ]);
        $workstations = Workstation::active()->orderBy('name')->get();

        return view('admin.process-templates.show', compact('productType', 'processTemplate', 'workstations'));
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

        return view('admin.process-templates.edit', compact('productType', 'processTemplate'));
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
            DB::transaction(function () use ($step, $previousStep) {
                $step->update(['step_number' => $step->step_number - 1]);
                $previousStep->update(['step_number' => $previousStep->step_number + 1]);
            });
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
            DB::transaction(function () use ($step, $nextStep) {
                $step->update(['step_number' => $step->step_number + 1]);
                $nextStep->update(['step_number' => $nextStep->step_number - 1]);
            });
        }

        return redirect()->route('admin.product-types.process-templates.show', [$productType, $processTemplate])
            ->with('success', 'Step moved down successfully.');
    }
}
