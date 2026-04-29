<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialType;
use Illuminate\Http\Request;

class MaterialManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Material::with('materialType')
            ->withCount('bomItems');

        if ($typeId = $request->query('material_type_id')) {
            $query->where('material_type_id', $typeId);
        }

        if ($search = $request->query('search')) {
            $needle = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(external_code) LIKE ?', [$needle]);
            });
        }

        $materials = $query->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        $materialTypes = MaterialType::orderBy('name')->get();

        return view('admin.materials.index', compact('materials', 'materialTypes'));
    }

    public function create()
    {
        $materialTypes = MaterialType::orderBy('name')->get();

        return view('admin.materials.create', compact('materialTypes'));
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

        return view('admin.materials.show', compact('material'));
    }

    public function edit(Material $material)
    {
        $materialTypes = MaterialType::orderBy('name')->get();

        return view('admin.materials.edit', compact('material', 'materialTypes'));
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
