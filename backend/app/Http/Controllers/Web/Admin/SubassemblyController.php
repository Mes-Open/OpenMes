<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Models\Subassembly;
use Illuminate\Http\Request;

class SubassemblyController extends Controller
{
    /**
     * Display a listing of subassemblies.
     */
    public function index(Request $request)
    {
        $query = Subassembly::with('productType')
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($productTypeId = $request->input('product_type_id')) {
            $query->where('product_type_id', $productTypeId);
        }

        $subassemblies = $query->paginate(25)->withQueryString();
        $productTypes  = ProductType::orderBy('name')->get();

        return view('admin.subassemblies.index', compact('subassemblies', 'productTypes'));
    }

    /**
     * Show the form for creating a new subassembly.
     */
    public function create()
    {
        $productTypes = ProductType::active()->orderBy('name')->get();

        return view('admin.subassemblies.create', compact('productTypes'));
    }

    /**
     * Store a newly created subassembly.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:50|unique:subassemblies',
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'product_type_id' => 'nullable|exists:product_types,id',
            'is_active'       => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Subassembly::create($validated);

        return redirect()->route('admin.subassemblies.index')
            ->with('success', 'Subassembly created successfully.');
    }

    /**
     * Show the form for editing a subassembly.
     */
    public function edit(Subassembly $subassembly)
    {
        $productTypes = ProductType::active()->orderBy('name')->get();

        return view('admin.subassemblies.edit', compact('subassembly', 'productTypes'));
    }

    /**
     * Update the specified subassembly.
     */
    public function update(Request $request, Subassembly $subassembly)
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:50|unique:subassemblies,code,' . $subassembly->id,
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'product_type_id' => 'nullable|exists:product_types,id',
            'is_active'       => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $subassembly->update($validated);

        return redirect()->route('admin.subassemblies.index')
            ->with('success', 'Subassembly updated successfully.');
    }

    /**
     * Remove the specified subassembly.
     */
    public function destroy(Subassembly $subassembly)
    {
        $subassembly->delete();

        return redirect()->route('admin.subassemblies.index')
            ->with('success', 'Subassembly deleted successfully.');
    }

    /**
     * Toggle subassembly active status.
     */
    public function toggleActive(Subassembly $subassembly)
    {
        $subassembly->update(['is_active' => ! $subassembly->is_active]);

        $status = $subassembly->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.subassemblies.index')
            ->with('success', "Subassembly {$status} successfully.");
    }
}
