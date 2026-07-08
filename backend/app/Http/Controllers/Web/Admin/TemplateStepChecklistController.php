<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateStepChecklistItemRequest;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStepChecklistItem;

/**
 * Checklist items on process template steps (admin authoring). Reusable
 * definition; operators tick them off per batch step at the workstation.
 * Routes are scoped to their template/product-type (mismatch = 404, IDOR).
 */
class TemplateStepChecklistController extends Controller
{
    public function store(
        StoreTemplateStepChecklistItemRequest $request,
        ProductType $productType,
        ProcessTemplate $processTemplate,
    ) {
        $this->ensureBelongs($productType, $processTemplate);

        $stepId = $request->validated('template_step_id');
        abort_unless($processTemplate->steps()->whereKey($stepId)->exists(), 404);

        $processTemplate->checklistItems()->create([
            'template_step_id' => $stepId,
            'label' => $request->validated('label'),
            'is_required' => $request->boolean('is_required'),
            'sort_order' => ($processTemplate->checklistItems()->where('template_step_id', $stepId)->max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Checklist item added.');
    }

    public function destroy(
        ProductType $productType,
        ProcessTemplate $processTemplate,
        TemplateStepChecklistItem $checklistItem,
    ) {
        $this->ensureBelongs($productType, $processTemplate);
        abort_unless($checklistItem->process_template_id === $processTemplate->id, 404);

        $checklistItem->delete();

        return back()->with('success', 'Checklist item removed.');
    }

    private function ensureBelongs(ProductType $productType, ProcessTemplate $processTemplate): void
    {
        abort_unless($processTemplate->product_type_id === $productType->id, 404);
    }
}
