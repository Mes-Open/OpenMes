<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SerialSequenceRequest;
use App\Models\ProductType;
use App\Models\SerialSequence;
use App\Rules\ValidLotPattern;
use App\Services\Lot\LotPatternFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin CRUD for serial-number sequences (#290) — parallel to
 * LotSequenceController, reusing the same LotPatternFormatter for token
 * rendering (it has no lot-specific behavior).
 */
class SerialSequenceController extends Controller
{
    use \App\Http\Controllers\Concerns\StaysOnList;

    public function index()
    {
        return Inertia::render('admin/serial-sequences/Index', [
            'productTypeNames' => fn () => ProductType::pluck('name', 'id'),
            'productTypes' => Inertia::optional(fn () => $this->activeProductTypes()),
            'patternTokens' => Inertia::optional(fn () => LotPatternFormatter::TOKENS),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/serial-sequences/Create', [
            'productTypes' => $this->activeProductTypes(),
            'patternTokens' => LotPatternFormatter::TOKENS,
        ]);
    }

    public function store(SerialSequenceRequest $request)
    {
        SerialSequence::create($request->payload());

        return $this->saved($request, redirect()->route('admin.serial-sequences.index'), __('Serial sequence created successfully.'));
    }

    public function edit(SerialSequence $serialSequence)
    {
        return Inertia::render('admin/serial-sequences/Edit', [
            'serialSequence' => $serialSequence->only(
                'id', 'name', 'product_type_id', 'prefix', 'suffix',
                'pattern', 'pad_size', 'year_prefix', 'reset_period',
            ),
            'productTypes' => $this->activeProductTypes(),
            'patternTokens' => LotPatternFormatter::TOKENS,
        ]);
    }

    public function update(SerialSequenceRequest $request, SerialSequence $serialSequence)
    {
        $serialSequence->update($request->payload());

        return $this->saved($request, redirect()->route('admin.serial-sequences.index'), __('Serial sequence updated successfully.'));
    }

    public function destroy(SerialSequence $serialSequence)
    {
        $serialSequence->delete();

        return redirect()->route('admin.serial-sequences.index')
            ->with('success', __('Serial sequence deleted successfully.'));
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

        $serial = (new LotPatternFormatter)->format(
            $validated['pattern'],
            1,
            $validated['pad_size'] ?? 4,
            $productCode,
            now(),
        );

        return response()->json(['preview' => $serial]);
    }

    private function activeProductTypes()
    {
        return ProductType::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
