<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use Illuminate\Http\Request;

class ProductTypeManagementController extends Controller
{
    /**
     * Display a listing of product types
     */
    public function index()
    {
        $productTypes = ProductType::withCount(['processTemplates', 'workOrders'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return view('admin.product-types.index', compact('productTypes'));
    }

    /**
     * Show the form for creating a new product type
     */
    public function create()
    {
        return view('admin.product-types.create');
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

        return view('admin.product-types.show', compact('productType', 'recentWorkOrders'));
    }

    /**
     * Show the form for editing a product type
     */
    public function edit(ProductType $productType)
    {
        return view('admin.product-types.edit', compact('productType'));
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
