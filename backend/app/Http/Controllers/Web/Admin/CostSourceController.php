<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostSource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CostSourceController extends Controller
{
    /**
     * Display a listing of cost sources. Rows live-sync via the
     * `cost_sources` shape; usage counts come as a prop.
     */
    public function index()
    {
        $counts = CostSource::withCount(['additionalCosts', 'maintenanceEvents'])
            ->get(['id'])
            ->mapWithKeys(fn ($r) => [$r->id => $r->additional_costs_count + $r->maintenance_events_count]);

        return Inertia::render('admin/cost-sources/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new cost source.
     */
    public function create()
    {
        return Inertia::render('admin/cost-sources/Create');
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
        // These columns are NOT NULL with DB defaults; a blank field arrives as
        // null (ConvertEmptyStringsToNull) and would trip the constraint on an
        // explicit insert, so restore the defaults.
        $validated['unit_cost'] ??= 0;
        $validated['unit'] ??= 'szt';
        $validated['currency'] ??= 'PLN';

        CostSource::create($validated);

        return redirect()->route('admin.cost-sources.index')
            ->with('success', 'Cost source created successfully.');
    }

    /**
     * Show the form for editing a cost source.
     */
    public function edit(CostSource $costSource)
    {
        return Inertia::render('admin/cost-sources/Edit', [
            'costSource' => $costSource->only('id', 'code', 'name', 'description', 'unit_cost', 'unit', 'currency', 'is_active'),
        ]);
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
        // NOT NULL columns with DB defaults — coerce a cleared field back rather
        // than passing an explicit null (which the DB default won't catch).
        $validated['unit_cost'] ??= 0;
        $validated['unit'] ??= 'szt';
        $validated['currency'] ??= 'PLN';

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
