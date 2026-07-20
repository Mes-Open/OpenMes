<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatchStepLotConsumption;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductTypeManagementController extends Controller
{
    /**
     * Display a listing of product types.
     *
     * The rows themselves live-sync via the `product_types` collection
     * (see Pages/admin/product-types/Index.jsx). Only the cross-table counts —
     * which don't map to per-row sync — are passed as a prop, keyed by id.
     */
    public function index()
    {
        $counts = ProductType::withCount(['processTemplates', 'workOrders'])
            ->get(['id'])
            ->mapWithKeys(fn ($pt) => [$pt->id => [
                'process_templates' => $pt->process_templates_count,
                'work_orders' => $pt->work_orders_count,
            ]]);

        return Inertia::render('admin/product-types/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new product type
     */
    public function create(CustomFieldService $cf)
    {
        return Inertia::render('admin/product-types/Create', [
            'customFields' => $cf->clientConfig('product_type'),
        ]);
    }

    /**
     * Store a newly created product type
     */
    public function store(Request $request, CustomFieldService $cf)
    {
        $validated = $request->validate(array_merge([
            'code' => 'required|string|max:50|unique:product_types',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ], $cf->rules('product_type')), [], $cf->attributeNames('product_type'));

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'product_type') ?: null;
        }

        ProductType::create($validated);

        return redirect()->route('admin.product-types.index')
            ->with('success', 'Product type created successfully.');
    }

    /**
     * Display the specified product type
     */
    public function show(ProductType $productType, CustomFieldService $cf)
    {
        $productType->load(['processTemplates.steps']);
        $recentWorkOrders = $productType->workOrders()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $totalWorkOrderCount = $productType->workOrders()->count();

        // Work order ids for this product type (tenant- and soft-delete-scoped
        // by the relation), reused for both the components and serials lookups.
        $workOrderIds = $productType->workOrders()->pluck('work_orders.id');

        $componentsUsed = $this->componentsConsumedBy($productType);
        $serials = $this->serialsProducedFor($workOrderIds);

        return Inertia::render('admin/product-types/Show', [
            'productType' => [
                'id' => $productType->id,
                'code' => $productType->code,
                'name' => $productType->name,
                'description' => $productType->description,
                'unit_of_measure' => $productType->unit_of_measure,
                'is_active' => $productType->is_active,
                'custom_fields' => $productType->custom_fields,
                'process_templates' => $productType->processTemplates->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'version' => $t->version,
                    'is_active' => $t->is_active,
                    'steps' => $t->steps->map(fn ($s) => ['id' => $s->id])->values(),
                ])->values(),
                'total_work_order_count' => $totalWorkOrderCount,
            ],
            'recentWorkOrders' => $recentWorkOrders->map(fn ($wo) => [
                'id' => $wo->id,
                // work_orders has `order_no`, not work_order_number; these orders
                // all belong to this product type, so product_name is its name.
                'work_order_number' => $wo->order_no,
                'product_name' => $productType->name,
                'planned_qty' => $wo->planned_qty,
                'status' => $wo->status,
                'created_at' => $wo->created_at?->toIso8601String(),
            ])->values(),
            'componentsUsed' => $componentsUsed,
            'serials' => $serials,
            'customFields' => $cf->clientConfig('product_type'),
        ]);
    }

    /**
     * Materials actually consumed while producing this product type, aggregated
     * across every (non-deleted) work order → batch → step → lot consumption.
     * This is real genealogy ("what went in"), not the planned BOM.
     */
    private function componentsConsumedBy(ProductType $productType): \Illuminate\Support\Collection
    {
        return BatchStepLotConsumption::query()
            ->join('batch_steps', 'batch_steps.id', '=', 'batch_step_lot_consumption.batch_step_id')
            ->join('batches', 'batches.id', '=', 'batch_steps.batch_id')
            ->join('work_orders', 'work_orders.id', '=', 'batches.work_order_id')
            ->join('material_lots', 'material_lots.id', '=', 'batch_step_lot_consumption.material_lot_id')
            ->join('materials', 'materials.id', '=', 'material_lots.material_id')
            ->where('work_orders.product_type_id', $productType->id)
            ->whereNull('batch_steps.deleted_at')
            ->whereNull('batches.deleted_at')
            ->whereNull('work_orders.deleted_at')
            ->whereNull('material_lots.deleted_at')
            ->whereNull('materials.deleted_at')
            ->groupBy('materials.id', 'materials.code', 'materials.name', 'materials.unit_of_measure')
            ->orderByDesc(DB::raw('SUM(batch_step_lot_consumption.quantity_consumed)'))
            ->get([
                'materials.id as id',
                'materials.code as code',
                'materials.name as name',
                'materials.unit_of_measure as unit_of_measure',
                DB::raw('SUM(batch_step_lot_consumption.quantity_consumed) as total_consumed'),
                DB::raw('COUNT(DISTINCT material_lots.id) as lot_count'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'unit_of_measure' => $row->unit_of_measure,
                'total_consumed' => (float) $row->total_consumed,
                'lot_count' => (int) $row->lot_count,
            ])
            ->values();
    }

    /**
     * Serialized units produced under the given work orders: total, a status
     * breakdown and the 20 most recent units (linked to the traceability
     * console by serial number).
     */
    private function serialsProducedFor(\Illuminate\Support\Collection $workOrderIds): array
    {
        $base = SerialUnit::query()->whereIn('work_order_id', $workOrderIds);

        $recent = (clone $base)
            ->with(['workOrder:id,order_no', 'batch:id,batch_number,lot_number'])
            ->orderByDesc('produced_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'status_counts' => (clone $base)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'recent' => $recent->map(fn (SerialUnit $unit) => [
                'id' => $unit->id,
                'serial_no' => $unit->serial_no,
                'status' => $unit->status,
                'produced_at' => $unit->produced_at?->toIso8601String(),
                'work_order' => $unit->workOrder?->order_no,
                'batch' => $unit->batch?->lot_number
                    ?? ($unit->batch ? '#'.$unit->batch->batch_number : null),
            ])->values(),
        ];
    }

    /**
     * Show the form for editing a product type
     */
    public function edit(ProductType $productType, CustomFieldService $cf)
    {
        return Inertia::render('admin/product-types/Edit', [
            'productType' => $productType->only(
                'id', 'code', 'name', 'description', 'unit_of_measure', 'is_active', 'custom_fields'
            ),
            'customFields' => $cf->clientConfig('product_type'),
        ]);
    }

    /**
     * Update the specified product type
     */
    public function update(Request $request, ProductType $productType, CustomFieldService $cf)
    {
        $validated = $request->validate(array_merge([
            'code' => 'required|string|max:50|unique:product_types,code,'.$productType->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ], $cf->rules('product_type')), [], $cf->attributeNames('product_type'));

        $validated['is_active'] = $request->boolean('is_active');
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'product_type', $productType->custom_fields) ?: null;
        }

        $productType->update($validated);

        return redirect()->route('admin.product-types.index')
            ->with('success', 'Product type updated successfully.');
    }

    /**
     * Remove the specified product type
     */
    public function destroy(ProductType $productType)
    {
        // Check if product type has work orders
        if ($productType->workOrders()->count() > 0) {
            return redirect()->route('admin.product-types.index')
                ->with('error', 'Cannot delete product type with existing work orders. Deactivate it instead.');
        }

        // Check if product type has process templates
        if ($productType->processTemplates()->count() > 0) {
            return redirect()->route('admin.product-types.index')
                ->with('error', 'Cannot delete product type with existing process templates. Deactivate it instead.');
        }

        $productType->delete();

        return redirect()->route('admin.product-types.index')
            ->with('success', 'Product type deleted successfully.');
    }

    /**
     * Toggle product type active status
     */
    public function toggleActive(ProductType $productType)
    {
        $productType->update(['is_active' => ! $productType->is_active]);

        $status = $productType->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.product-types.index')
            ->with('success', "Product type {$status} successfully.");
    }
}
