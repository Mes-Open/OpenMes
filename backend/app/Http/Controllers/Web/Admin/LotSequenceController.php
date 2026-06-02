<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\LotSequence;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LotSequenceController extends Controller
{
    public function index()
    {
        $productTypeNames = ProductType::pluck('name', 'id');

        return Inertia::render('admin/lot-sequences/Index', [
            'productTypeNames' => $productTypeNames,
        ]);
    }

    public function create()
    {
        $productTypes = ProductType::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/lot-sequences/Create', [
            'productTypes' => $productTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'product_type_id' => 'nullable|exists:product_types,id|unique:lot_sequences,product_type_id',
            'prefix' => 'required|string|max:20',
            'suffix' => 'nullable|string|max:20',
            'pad_size' => 'nullable|integer|min:1|max:10',
            'year_prefix' => 'boolean',
        ]);

        $validated['year_prefix'] = $request->boolean('year_prefix', true);
        $validated['pad_size'] = $validated['pad_size'] ?? 4;

        LotSequence::create($validated);

        return redirect()->route('admin.lot-sequences.index')
            ->with('success', 'LOT sequence created successfully.');
    }

    public function edit(LotSequence $lotSequence)
    {
        $productTypes = ProductType::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/lot-sequences/Edit', [
            'lotSequence' => $lotSequence->only('id', 'name', 'product_type_id', 'prefix', 'suffix', 'pad_size', 'year_prefix'),
            'productTypes' => $productTypes,
        ]);
    }

    public function update(Request $request, LotSequence $lotSequence)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'product_type_id' => 'nullable|exists:product_types,id|unique:lot_sequences,product_type_id,'.$lotSequence->id,
            'prefix' => 'required|string|max:20',
            'suffix' => 'nullable|string|max:20',
            'pad_size' => 'required|integer|min:1|max:10',
            'year_prefix' => 'boolean',
        ]);

        $validated['year_prefix'] = $request->boolean('year_prefix');

        $lotSequence->update($validated);

        return redirect()->route('admin.lot-sequences.index')
            ->with('success', 'LOT sequence updated successfully.');
    }

    public function destroy(LotSequence $lotSequence)
    {
        $lotSequence->delete();

        return redirect()->route('admin.lot-sequences.index')
            ->with('success', 'LOT sequence deleted successfully.');
    }
}
