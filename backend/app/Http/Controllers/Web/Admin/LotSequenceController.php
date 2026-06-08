<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LotSequenceRequest;
use App\Models\LotSequence;
use App\Models\ProductType;
use App\Rules\ValidLotPattern;
use App\Services\Lot\LotPatternFormatter;
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
        return Inertia::render('admin/lot-sequences/Create', [
            'productTypes' => $this->activeProductTypes(),
            'patternTokens' => LotPatternFormatter::TOKENS,
        ]);
    }

    public function store(LotSequenceRequest $request)
    {
        LotSequence::create($request->payload());

        return redirect()->route('admin.lot-sequences.index')
            ->with('success', 'LOT sequence created successfully.');
    }

    public function edit(LotSequence $lotSequence)
    {
        return Inertia::render('admin/lot-sequences/Edit', [
            'lotSequence' => $lotSequence->only(
                'id', 'name', 'product_type_id', 'prefix', 'suffix',
                'pattern', 'pad_size', 'year_prefix', 'reset_period',
            ),
            'productTypes' => $this->activeProductTypes(),
            'patternTokens' => LotPatternFormatter::TOKENS,
        ]);
    }

    public function update(LotSequenceRequest $request, LotSequence $lotSequence)
    {
        $lotSequence->update($request->payload());

        return redirect()->route('admin.lot-sequences.index')
            ->with('success', 'LOT sequence updated successfully.');
    }

    public function destroy(LotSequence $lotSequence)
    {
        $lotSequence->delete();

        return redirect()->route('admin.lot-sequences.index')
            ->with('success', 'LOT sequence deleted successfully.');
    }

    /**
     * Live preview of a pattern from the form (nothing is persisted).
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'pattern' => ['required', 'string', 'max:100', new ValidLotPattern],
            'pad_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'product_type_id' => ['nullable', 'exists:product_types,id'],
        ]);

        $productCode = isset($validated['product_type_id'])
            ? ProductType::find($validated['product_type_id'])?->code
            : null;

        $lot = (new LotPatternFormatter)->format(
            $validated['pattern'],
            1,
            $validated['pad_size'] ?? 4,
            $productCode,
            now(),
        );

        return response()->json(['preview' => $lot]);
    }

    private function activeProductTypes()
    {
        return ProductType::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
