<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreWageGroupRequest;
use App\Http\Requests\Web\Admin\UpdateWageGroupRequest;
use App\Models\WageGroup;
use Inertia\Inertia;

class WageGroupController extends Controller
{
    /**
     * Display a listing of wage groups. Rows live-sync via the
     * `wage_groups` shape; worker counts come as a prop.
     */
    public function index()
    {
        $counts = WageGroup::withCount('workers')
            ->get(['id'])
            ->mapWithKeys(fn ($r) => [$r->id => $r->workers_count]);

        return Inertia::render('admin/wage-groups/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new wage group.
     */
    public function create()
    {
        return Inertia::render('admin/wage-groups/Create');
    }

    /**
     * Store a newly created wage group.
     */
    public function store(StoreWageGroupRequest $request)
    {
        WageGroup::create($request->validated());

        return redirect()->route('admin.wage-groups.index')
            ->with('success', 'Wage group created successfully.');
    }

    /**
     * Show the form for editing a wage group.
     */
    public function edit(WageGroup $wageGroup)
    {
        return Inertia::render('admin/wage-groups/Edit', [
            'wageGroup' => $wageGroup->only('id', 'code', 'name', 'description', 'base_hourly_rate', 'currency', 'is_active'),
        ]);
    }

    /**
     * Update the specified wage group.
     */
    public function update(UpdateWageGroupRequest $request, WageGroup $wageGroup)
    {
        $wageGroup->update($request->validated());

        return redirect()->route('admin.wage-groups.index')
            ->with('success', 'Wage group updated successfully.');
    }

    /**
     * Remove the specified wage group.
     */
    public function destroy(WageGroup $wageGroup)
    {
        if ($wageGroup->workers()->count() > 0) {
            return redirect()->route('admin.wage-groups.index')
                ->with('error', 'Cannot delete wage group with assigned workers. Deactivate it instead.');
        }

        $wageGroup->delete();

        return redirect()->route('admin.wage-groups.index')
            ->with('success', 'Wage group deleted successfully.');
    }

    /**
     * Toggle wage group active status.
     */
    public function toggleActive(WageGroup $wageGroup)
    {
        $wageGroup->update(['is_active' => ! $wageGroup->is_active]);

        $status = $wageGroup->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.wage-groups.index')
            ->with('success', "Wage group {$status} successfully.");
    }
}
