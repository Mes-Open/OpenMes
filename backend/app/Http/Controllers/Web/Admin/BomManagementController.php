<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Services\Material\BomService;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        $bomItems  = $this->bomService->listForTemplate($processTemplate);
        $materials = Material::active()->with('materialType')->orderBy('name')->get();
        $steps     = $processTemplate->steps()->orderBy('step_number')->get();

        return Inertia::render('admin/process-templates/Bom', [
            'productType'     => $productType->only('id', 'name'),
            'processTemplate' => [
                'id'      => $processTemplate->id,
                'name'    => $processTemplate->name,
                'version' => $processTemplate->version,
            ],
            'bomItems'        => $bomItems->map(fn($item) => [
                'id'                 => $item->id,
                'material_name'      => $item->material->name,
                'material_code'      => $item->material->code,
                'material_type_name' => $item->material->materialType->name,
                'material_type_code' => $item->material->materialType->code,
                'unit_of_measure'    => $item->material->unit_of_measure,
                'tracking_type'      => $item->material->tracking_type,
                'step_number'        => $item->templateStep?->step_number,
                'step_name'          => $item->templateStep?->name,
                'quantity_per_unit'  => $item->quantity_per_unit,
                'scrap_percentage'   => $item->scrap_percentage,
                'consumed_at'        => $item->consumed_at,
                'notes'              => $item->notes,
            ]),
            'materials'       => $materials->map(fn($m) => [
                'id'                 => $m->id,
                'code'               => $m->code,
                'name'               => $m->name,
                'material_type_name' => $m->materialType->name,
            ]),
            'steps'           => $steps->map(fn($s) => [
                'id'          => $s->id,
                'step_number' => $s->step_number,
                'name'        => $s->name,
            ]),
        ]);
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
