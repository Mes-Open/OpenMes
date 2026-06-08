<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductTypeManagementController extends Controller
{
    /**
     * Display a listing of product types.
     *
     * The rows themselves live-sync via the `product_types` Electric shape
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
    public function create()
    {
        return Inertia::render('admin/product-types/Create');
    }

    /**
     * Store a newly created product type
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:product_types',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';

        ProductType::create($validated);

        return redirect()->route('admin.product-types.index')
            ->with('success', 'Product type created successfully.');
    }

    /**
     * Display the specified product type
     */
    public function show(ProductType $productType)
    {
        $productType->load(['processTemplates.steps']);
        $recentWorkOrders = $productType->workOrders()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $totalWorkOrderCount = $productType->workOrders()->count();

        return Inertia::render('admin/product-types/Show', [
            'productType' => [
                'id'               => $productType->id,
                'code'             => $productType->code,
                'name'             => $productType->name,
                'description'      => $productType->description,
                'unit_of_measure'  => $productType->unit_of_measure,
                'is_active'        => $productType->is_active,
                'process_templates' => $productType->processTemplates->map(fn ($t) => [
                    'id'        => $t->id,
                    'name'      => $t->name,
                    'version'   => $t->version,
                    'is_active' => $t->is_active,
                    'steps'     => $t->steps->map(fn ($s) => ['id' => $s->id])->values(),
                ])->values(),
                'total_work_order_count' => $totalWorkOrderCount,
            ],
            'recentWorkOrders' => $recentWorkOrders->map(fn ($wo) => [
                'id'                => $wo->id,
                // work_orders has `order_no`, not work_order_number; these orders
                // all belong to this product type, so product_name is its name.
                'work_order_number' => $wo->order_no,
                'product_name'      => $productType->name,
                'planned_qty'       => $wo->planned_qty,
                'status'            => $wo->status,
                'created_at'        => $wo->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Show the form for editing a product type
     */
    public function edit(ProductType $productType)
    {
        return Inertia::render('admin/product-types/Edit', [
            'productType' => $productType->only(
                'id', 'code', 'name', 'description', 'unit_of_measure', 'is_active'
            ),
        ]);
    }

    /**
     * Update the specified product type
     */
    public function update(Request $request, ProductType $productType)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:product_types,code,' . $productType->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';

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
        $productType->update(['is_active' => !$productType->is_active]);

        $status = $productType->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.product-types.index')
            ->with('success', "Product type {$status} successfully.");
    }
}
