<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Models\MaterialType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MaterialTypeController extends Controller
{
    use StaysOnList;

    /**
     * Display a listing of material types. Rows live-sync via the
     * `material_types` shape; only the material counts (cross-table) come as a prop.
     */
    public function index()
    {
        $counts = MaterialType::withCount('materials')
            ->get(['id'])
            ->mapWithKeys(fn ($t) => [$t->id => $t->materials_count]);

        return Inertia::render('admin/material-types/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new material type.
     */
    public function create()
    {
        return Inertia::render('admin/material-types/Create');
    }

    /**
     * Store a newly created material type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:material_types,code',
            'name' => 'required|string|max:100',
        ]);

        MaterialType::create($validated);

        return $this->saved($request, redirect()->route('admin.material-types.index'), __('Material type created successfully.'));
    }

    /**
     * Show the form for editing a material type.
     */
    public function edit(MaterialType $materialType)
    {
        return Inertia::render('admin/material-types/Edit', [
            'materialType' => $materialType->only('id', 'code', 'name'),
        ]);
    }

    /**
     * Update the specified material type.
     */
    public function update(Request $request, MaterialType $materialType)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('material_types', 'code')->ignore($materialType->id)],
            'name' => 'required|string|max:100',
        ]);

        $materialType->update($validated);

        return $this->saved($request, redirect()->route('admin.material-types.index'), __('Material type updated successfully.'));
    }

    /**
     * Remove the specified material type.
     */
    public function destroy(MaterialType $materialType)
    {
        if ($materialType->materials()->exists()) {
            return redirect()->route('admin.material-types.index')
                ->with('error', __('Cannot delete a material type assigned to materials. Reassign those materials first.'));
        }

        $materialType->delete();

        return redirect()->route('admin.material-types.index')
            ->with('success', __('Material type deleted successfully.'));
    }
}
