<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkstationType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkstationTypeController extends Controller
{
    /**
     * Display a listing of workstation types. Rows live-sync via the
     * `workstation_types` shape; workstation counts come as a prop.
     */
    public function index()
    {
        $counts = WorkstationType::withCount('workstations')
            ->get(['id'])
            ->mapWithKeys(fn ($w) => [$w->id => $w->workstations_count]);

        return Inertia::render('admin/workstation-types/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new workstation type.
     */
    public function create()
    {
        return Inertia::render('admin/workstation-types/Create');
    }

    /**
     * Store a newly created workstation type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:workstation_types',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        WorkstationType::create($validated);

        return redirect()->route('admin.workstation-types.index')
            ->with('success', 'Workstation type created successfully.');
    }

    /**
     * Show the form for editing a workstation type.
     */
    public function edit(WorkstationType $workstationType)
    {
        return Inertia::render('admin/workstation-types/Edit', [
            'workstationType' => $workstationType->only('id', 'code', 'name', 'description', 'is_active'),
        ]);
    }

    /**
     * Update the specified workstation type.
     */
    public function update(Request $request, WorkstationType $workstationType)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:workstation_types,code,' . $workstationType->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $workstationType->update($validated);

        return redirect()->route('admin.workstation-types.index')
            ->with('success', 'Workstation type updated successfully.');
    }

    /**
     * Remove the specified workstation type.
     */
    public function destroy(WorkstationType $workstationType)
    {
        if ($workstationType->workstations()->count() > 0) {
            return redirect()->route('admin.workstation-types.index')
                ->with('error', 'Cannot delete workstation type with existing workstations. Deactivate it instead.');
        }

        if ($workstationType->tools()->count() > 0) {
            return redirect()->route('admin.workstation-types.index')
                ->with('error', 'Cannot delete workstation type with associated tools. Deactivate it instead.');
        }

        $workstationType->delete();

        return redirect()->route('admin.workstation-types.index')
            ->with('success', 'Workstation type deleted successfully.');
    }

    /**
     * Toggle workstation type active status.
     */
    public function toggleActive(WorkstationType $workstationType)
    {
        $workstationType->update(['is_active' => ! $workstationType->is_active]);

        $status = $workstationType->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.workstation-types.index')
            ->with('success', "Workstation type {$status} successfully.");
    }
}
