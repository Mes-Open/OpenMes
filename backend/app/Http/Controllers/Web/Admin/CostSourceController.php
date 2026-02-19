<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostSource;
use Illuminate\Http\Request;

class CostSourceController extends Controller
{
    /**
     * Display a listing of cost sources.
     */
    public function index(Request $request)
    {
        $query = CostSource::withCount(['additionalCosts', 'maintenanceEvents'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $costSources = $query->paginate(25)->withQueryString();

        return view('admin.cost-sources.index', compact('costSources'));
    }

    /**
     * Show the form for creating a new cost source.
     */
    public function create()
    {
        return view('admin.cost-sources.create');
    }

    /**
     * Store a newly created cost source.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:cost_sources',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'unit_cost'   => 'nullable|numeric|min:0',
            'unit'        => 'nullable|string|max:50',
            'currency'    => 'nullable|string|max:10',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        CostSource::create($validated);

        return redirect()->route('admin.cost-sources.index')
            ->with('success', 'Cost source created successfully.');
    }

    /**
     * Show the form for editing a cost source.
     */
    public function edit(CostSource $costSource)
    {
        return view('admin.cost-sources.edit', compact('costSource'));
    }

    /**
     * Update the specified cost source.
     */
    public function update(Request $request, CostSource $costSource)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:cost_sources,code,' . $costSource->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'unit_cost'   => 'nullable|numeric|min:0',
            'unit'        => 'nullable|string|max:50',
            'currency'    => 'nullable|string|max:10',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $costSource->update($validated);

        return redirect()->route('admin.cost-sources.index')
            ->with('success', 'Cost source updated successfully.');
    }

    /**
     * Remove the specified cost source.
     */
    public function destroy(CostSource $costSource)
    {
        if ($costSource->additionalCosts()->count() > 0 || $costSource->maintenanceEvents()->count() > 0) {
            return redirect()->route('admin.cost-sources.index')
                ->with('error', 'Cannot delete cost source with existing usage records. Deactivate it instead.');
        }

        $costSource->delete();

        return redirect()->route('admin.cost-sources.index')
            ->with('success', 'Cost source deleted successfully.');
    }

    /**
     * Toggle cost source active status.
     */
    public function toggleActive(CostSource $costSource)
    {
        $costSource->update(['is_active' => ! $costSource->is_active]);

        $status = $costSource->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.cost-sources.index')
            ->with('success', "Cost source {$status} successfully.");
    }
}
