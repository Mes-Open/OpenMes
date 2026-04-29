<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Services\Material\BomService;
use Illuminate\Http\Request;

class BomManagementController extends Controller
{
    public function __construct(private BomService $bomService) {}

    /**
     * Display BOM items for a process template (shown as a tab on template show page).
     */
    public function index(ProductType $productType, ProcessTemplate $processTemplate)
    {
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $bomItems = $this->bomService->listForTemplate($processTemplate);
        $materials = Material::active()->with('materialType')->orderBy('name')->get();
        $steps = $processTemplate->steps()->orderBy('step_number')->get();

        return view('admin.process-templates.bom', compact(
            'productType', 'processTemplate', 'bomItems', 'materials', 'steps'
        ));
    }

    public function store(Request $request, ProductType $productType, ProcessTemplate $processTemplate)
    {
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id|unique:bom_items,material_id,NULL,id,process_template_id,'.$processTemplate->id,
            'template_step_id' => 'nullable|exists:template_steps,id',
            'quantity_per_unit' => 'required|numeric|gt:0',
            'scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'consumed_at' => 'nullable|in:start,during,end',
            'notes' => 'nullable|string',
        ]);

        $this->bomService->addItem($processTemplate, $validated);

        return redirect()->route('admin.product-types.process-templates.bom', [$productType, $processTemplate])
            ->with('success', 'Material added to BOM.');
    }

    public function update(Request $request, ProductType $productType, ProcessTemplate $processTemplate, BomItem $bomItem)
    {
        if ($processTemplate->product_type_id !== $productType->id || $bomItem->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        $validated = $request->validate([
            'template_step_id' => 'nullable|exists:template_steps,id',
            'quantity_per_unit' => 'required|numeric|gt:0',
            'scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'consumed_at' => 'nullable|in:start,during,end',
            'notes' => 'nullable|string',
        ]);

        $this->bomService->updateItem($bomItem, $validated);

        return redirect()->route('admin.product-types.process-templates.bom', [$productType, $processTemplate])
            ->with('success', 'BOM item updated.');
    }

    public function destroy(ProductType $productType, ProcessTemplate $processTemplate, BomItem $bomItem)
    {
        if ($processTemplate->product_type_id !== $productType->id || $bomItem->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        $this->bomService->removeItem($bomItem);

        return redirect()->route('admin.product-types.process-templates.bom', [$productType, $processTemplate])
            ->with('success', 'Material removed from BOM.');
    }
}
