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

        $bomItems = $this->bomService->listForTemplate($processTemplate);
        $materials = Material::active()->with('materialType')->orderBy('name')->get();
        // Product types that can be added as sub-assembly components — every active
        // one except this template's own product type (a product can't contain itself).
        $productTypes = ProductType::active()
            ->where('id', '!=', $productType->id)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit_of_measure']);
        $steps = $processTemplate->steps()->orderBy('step_number')->get();

        return Inertia::render('admin/process-templates/Bom', [
            'productType' => $productType->only('id', 'name'),
            'processTemplate' => [
                'id' => $processTemplate->id,
                'name' => $processTemplate->name,
                'version' => $processTemplate->version,
            ],
            'bomItems' => $bomItems->map(function ($item) {
                $isProductType = $item->component_kind === 'product_type';

                return [
                    'id' => $item->id,
                    'component_kind' => $item->component_kind,
                    // Generic component name/code the table renders for both kinds.
                    'component_name' => $isProductType ? $item->productType?->name : $item->material?->name,
                    'component_code' => $isProductType ? $item->productType?->code : $item->material?->code,
                    'material_id' => $item->material_id,
                    'product_type_id' => $item->product_type_id,
                    // Material-only metadata (null for product-type lines).
                    'material_type_name' => $item->material?->materialType->name,
                    'material_type_code' => $item->material?->materialType->code,
                    'unit_of_measure' => $isProductType ? $item->productType?->unit_of_measure : $item->material?->unit_of_measure,
                    'tracking_type' => $item->material?->tracking_type,
                    'template_step_id' => $item->template_step_id,
                    'step_number' => $item->templateStep?->step_number,
                    'step_name' => $item->templateStep?->name,
                    'quantity_per_unit' => $item->quantity_per_unit,
                    'scrap_percentage' => $item->scrap_percentage,
                    'consumed_at' => $item->consumed_at,
                    'notes' => $item->notes,
                ];
            }),
            'materials' => $materials->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'material_type_name' => $m->materialType->name,
                'unit_of_measure' => $m->unit_of_measure,
                'default_scrap_percentage' => $m->default_scrap_percentage,
            ]),
            'productTypes' => $productTypes->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'unit_of_measure' => $p->unit_of_measure,
            ]),
            'steps' => $steps->map(fn ($s) => [
                'id' => $s->id,
                'step_number' => $s->step_number,
                'name' => $s->name,
            ]),
        ]);
    }

    public function store(Request $request, ProductType $productType, ProcessTemplate $processTemplate)
    {
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        // A line is exactly one of material / product-type. `required_without` +
        // `prohibits` enforces exactly-one; `component_kind` (sent by the UI) is
        // ignored so older material-only callers keep working.
        $validated = $request->validate([
            'material_id' => [
                'required_without:product_type_id',
                'prohibits:product_type_id',
                'nullable',
                'exists:materials,id',
                \Illuminate\Validation\Rule::unique('bom_items', 'material_id')
                    ->where('process_template_id', $processTemplate->id)
                    ->whereNull('deleted_at'),
            ],
            'product_type_id' => [
                'required_without:material_id',
                'nullable',
                'exists:product_types,id',
                // A product can't be a component of itself.
                \Illuminate\Validation\Rule::notIn([$productType->id]),
                \Illuminate\Validation\Rule::unique('bom_items', 'product_type_id')
                    ->where('process_template_id', $processTemplate->id)
                    ->whereNull('deleted_at'),
            ],
            'template_step_id' => 'nullable|exists:template_steps,id',
            'quantity_per_unit' => 'required|numeric|gt:0',
            'scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'consumed_at' => 'nullable|in:start,during,end',
            'notes' => 'nullable|string',
        ], [
            'material_id.unique' => __('This material is already in the BOM for this template.'),
            'product_type_id.unique' => __('This product type is already in the BOM for this template.'),
            'product_type_id.not_in' => __('A product type cannot be a component of itself.'),
        ]);

        // Persist only the component reference that was set.
        $data = [
            'material_id' => $validated['material_id'] ?? null,
            'product_type_id' => $validated['product_type_id'] ?? null,
            'template_step_id' => $validated['template_step_id'] ?? null,
            'quantity_per_unit' => $validated['quantity_per_unit'],
            'scrap_percentage' => $validated['scrap_percentage'] ?? null,
            'consumed_at' => $validated['consumed_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $this->bomService->addItem($processTemplate, $data);

        return redirect()->route('admin.product-types.process-templates.bom', [$productType, $processTemplate])
            ->with('success', __('Component added to BOM.'));
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
