<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreBomItemRequest;
use App\Http\Requests\Web\Admin\UpdateBomItemRequest;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Services\Material\BomService;
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
                    'material_type_name' => $item->material?->materialType?->name,
                    'material_type_code' => $item->material?->materialType?->code,
                    'unit_of_measure' => $isProductType ? $item->productType?->unit_of_measure : $item->material?->unit_of_measure,
                    'tracking_type' => $item->material?->tracking_type,
                    'template_step_id' => $item->template_step_id,
                    'step_number' => $item->templateStep?->step_number,
                    'step_name' => $item->templateStep?->name,
                    'quantity_per_unit' => $item->quantity_per_unit,
                    'per' => $item->per ?? BomItem::PER_UNIT,
                    'scrap_percentage' => $item->scrap_percentage,
                    'consumed_at' => $item->consumed_at,
                    'notes' => $item->notes,
                ];
            }),
            'materials' => $materials->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'material_type_name' => $m->materialType?->name,
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
                'kind' => $s->kind,
                'config' => $s->config,
            ]),
        ]);
    }

    public function store(StoreBomItemRequest $request, ProductType $productType, ProcessTemplate $processTemplate)
    {
        if ($processTemplate->product_type_id !== $productType->id) {
            abort(404);
        }

        $validated = $request->validated();

        // Persist only the component reference that was set.
        $data = [
            'material_id' => $validated['material_id'] ?? null,
            'product_type_id' => $validated['product_type_id'] ?? null,
            'template_step_id' => $validated['template_step_id'] ?? null,
            'quantity_per_unit' => $validated['quantity_per_unit'],
            'per' => $validated['per'] ?? BomItem::PER_UNIT,
            'scrap_percentage' => $validated['scrap_percentage'] ?? null,
            'consumed_at' => $validated['consumed_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $this->bomService->addItem($processTemplate, $data);

        return redirect()->route('admin.product-types.process-templates.bom', [$productType, $processTemplate])
            ->with('success', __('Component added to BOM.'));
    }

    public function update(UpdateBomItemRequest $request, ProductType $productType, ProcessTemplate $processTemplate, BomItem $bomItem)
    {
        if ($processTemplate->product_type_id !== $productType->id || $bomItem->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        $validated = $request->validated();
        // A caller that does not send the basis keeps the line's: a per-carton
        // line must not silently turn per-unit (and its need grow by the box size).
        $validated['per'] = $validated['per'] ?? ($request->has('per') ? BomItem::PER_UNIT : ($bomItem->per ?? BomItem::PER_UNIT));

        $this->bomService->updateItem($bomItem, $validated);

        return redirect()->route('admin.product-types.process-templates.bom', [$productType, $processTemplate])
            ->with('success', __('BOM item updated.'));
    }

    public function destroy(ProductType $productType, ProcessTemplate $processTemplate, BomItem $bomItem)
    {
        if ($processTemplate->product_type_id !== $productType->id || $bomItem->process_template_id !== $processTemplate->id) {
            abort(404);
        }

        $this->bomService->removeItem($bomItem);

        return redirect()->route('admin.product-types.process-templates.bom', [$productType, $processTemplate])
            ->with('success', __('Material removed from BOM.'));
    }
}
