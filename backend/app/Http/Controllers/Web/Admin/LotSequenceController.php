<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\LotSequence;
use App\Models\ProductType;
use Illuminate\Http\Request;

class LotSequenceController extends Controller
{
    public function index()
    {
        $sequences = LotSequence::with('productType')
            ->orderBy('name')
            ->get();

        return view('admin.lot-sequences.index', compact('sequences'));
    }

    public function create()
    {
        $productTypes = ProductType::active()
            ->whereDoesntHave('lotSequence')
            ->orderBy('name')
            ->get();

        return view('admin.lot-sequences.create', compact('productTypes'));
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
        $productTypes = ProductType::active()
            ->where(function ($q) use ($lotSequence) {
                $q->whereDoesntHave('lotSequence')
                    ->orWhere('id', $lotSequence->product_type_id);
            })
            ->orderBy('name')
            ->get();

        return view('admin.lot-sequences.edit', compact('lotSequence', 'productTypes'));
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
