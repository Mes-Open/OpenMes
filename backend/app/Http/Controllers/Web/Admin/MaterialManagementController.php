<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaterialManagementController extends Controller
{
    public function index()
    {
        $counts = Material::withCount('bomItems')
            ->get(['id'])
            ->mapWithKeys(fn ($m) => [$m->id => $m->bom_items_count]);

        return Inertia::render('admin/materials/Index', [
            'counts' => $counts,
            'materialTypeNames' => MaterialType::pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/materials/Create', [
            'materialTypes' => MaterialType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:materials',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'material_type_id' => 'required|exists:material_types,id',
            'unit_of_measure' => 'nullable|string|max:20',
            'tracking_type' => 'nullable|in:none,batch,serial',
            'default_scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'external_code' => 'nullable|string|max:100',
            'external_system' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        $validated['tracking_type'] = $validated['tracking_type'] ?? 'none';

        Material::create($validated);

        return redirect()->route('admin.materials.index')
            ->with('success', 'Material created successfully.');
    }

    public function show(Material $material)
    {
        $material->load(['materialType', 'sources.integrationConfig', 'bomItems.processTemplate.productType']);

        $lots = \App\Models\MaterialLot::where('material_id', $material->id)
            ->orderByRaw("CASE WHEN status = 'available' THEN 0 ELSE 1 END")
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->limit(20)
            ->get()
            ->map(fn ($lot) => [
                'id'                => $lot->id,
                'lot_number'        => $lot->lot_number,
                'supplier_lot_no'   => $lot->supplier_lot_no,
                'quantity_received' => $lot->quantity_received,
                'quantity_available'=> $lot->quantity_available,
                'expiry_date'       => $lot->expiry_date?->toDateString(),
                'status'            => $lot->status,
                'is_expired'        => $lot->isExpired(),
            ]);

        $recentMovements = \App\Models\StockMovement::forMaterial($material->id)
            ->limit(15)
            ->get()
            ->map(fn ($mv) => [
                'id'            => $mv->id,
                'performed_at'  => $mv->performed_at?->toIso8601String(),
                'movement_type' => $mv->movement_type,
                'quantity'      => $mv->quantity,
                'balance_after' => $mv->balance_after,
                'source_type'   => $mv->source_type,
                'source_id'     => $mv->source_id,
                'reason'        => $mv->reason,
                'performed_by'  => $mv->performedBy ? ['name' => $mv->performedBy->name] : null,
            ]);

        return Inertia::render('admin/materials/Show', [
            'material' => [
                'id'                       => $material->id,
                'code'                     => $material->code,
                'name'                     => $material->name,
                'is_active'                => $material->is_active,
                'unit_of_measure'          => $material->unit_of_measure,
                'tracking_type'            => $material->tracking_type,
                'default_scrap_percentage' => $material->default_scrap_percentage,
                'stock_quantity'           => $material->stock_quantity,
                'reserved_quantity'        => $material->reserved_quantity ?? 0,
                'available_quantity'       => $material->available_quantity,
                'min_stock_level'          => $material->min_stock_level,
                'unit_price'               => $material->unit_price,
                'price_currency'           => $material->price_currency,
                'external_code'            => $material->external_code,
                'external_system'          => $material->external_system,
                'material_type'            => $material->materialType ? ['name' => $material->materialType->name] : null,
                'sources'                  => $material->sources->map(fn ($s) => [
                    'id'               => $s->id,
                    'external_code'    => $s->external_code,
                    'integration_config' => $s->integrationConfig ? ['system_name' => $s->integrationConfig->system_name] : null,
                ])->values(),
                'bom_items'                => $material->bomItems->map(fn ($item) => [
                    'id'               => $item->id,
                    'quantity_per_unit' => $item->quantity_per_unit,
                    'scrap_percentage' => $item->scrap_percentage,
                    'process_template' => $item->processTemplate ? [
                        'name'         => $item->processTemplate->name,
                        'product_type' => $item->processTemplate->productType
                            ? ['name' => $item->processTemplate->productType->name]
                            : null,
                    ] : null,
                ])->values(),
            ],
            'lots'            => $lots,
            'recentMovements' => $recentMovements,
        ]);
    }

    public function edit(Material $material)
    {
        return Inertia::render('admin/materials/Edit', [
            'material' => $material->only(
                'id', 'code', 'name', 'description', 'material_type_id', 'unit_of_measure',
                'tracking_type', 'default_scrap_percentage', 'external_code', 'external_system', 'is_active'
            ),
            'materialTypes' => MaterialType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Material $material)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:materials,code,'.$material->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'material_type_id' => 'required|exists:material_types,id',
            'unit_of_measure' => 'nullable|string|max:20',
            'tracking_type' => 'nullable|in:none,batch,serial',
            'default_scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'external_code' => 'nullable|string|max:100',
            'external_system' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $material->update($validated);

        return redirect()->route('admin.materials.index')
            ->with('success', 'Material updated successfully.');
    }

    public function destroy(Material $material)
    {
        if ($material->bomItems()->exists()) {
            return redirect()->route('admin.materials.index')
                ->with('error', 'Cannot delete material used in BOM. Deactivate it instead.');
        }

        $material->delete();

        return redirect()->route('admin.materials.index')
            ->with('success', 'Material deleted successfully.');
    }

    public function toggleActive(Material $material)
    {
        $material->update(['is_active' => ! $material->is_active]);

        $status = $material->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.materials.index')
            ->with('success', "Material {$status} successfully.");
    }
}
