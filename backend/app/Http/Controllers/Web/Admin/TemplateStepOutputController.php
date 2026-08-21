<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateStepOutputRequest;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStepOutput;

/**
 * Typed operator-output definitions on process template steps (admin authoring).
 * Reusable definition; operators record a value per batch step at the
 * workstation. Routes are scoped to their template/product-type (mismatch = 404).
 */
class TemplateStepOutputController extends Controller
{
    public function store(
        StoreTemplateStepOutputRequest $request,
        ProductType $productType,
        ProcessTemplate $processTemplate,
    ) {
        $this->ensureBelongs($productType, $processTemplate);

        $stepId = $request->validated('template_step_id');
        abort_unless($processTemplate->steps()->whereKey($stepId)->exists(), 404);

        $processTemplate->outputs()->create([
            'template_step_id' => $stepId,
            'key' => $request->validated('key'),
            'label' => $request->validated('label'),
            'value_type' => $request->validated('value_type'),
            'unit' => $request->validated('unit'),
            'options' => $request->validated('options'),
            'is_required' => $request->boolean('is_required'),
            'sort_order' => ($processTemplate->outputs()->where('template_step_id', $stepId)->max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', __('Output added.'));
    }

    public function destroy(
        ProductType $productType,
        ProcessTemplate $processTemplate,
        TemplateStepOutput $output,
    ) {
        $this->ensureBelongs($productType, $processTemplate);
        abort_unless($output->process_template_id === $processTemplate->id, 404);

        $output->delete();

        return back()->with('success', __('Output removed.'));
    }

    private function ensureBelongs(ProductType $productType, ProcessTemplate $processTemplate): void
    {
        abort_unless($processTemplate->product_type_id === $productType->id, 404);
    }
}
