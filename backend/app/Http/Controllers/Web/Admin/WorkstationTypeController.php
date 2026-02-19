<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkstationType;
use Illuminate\Http\Request;

class WorkstationTypeController extends Controller
{
    /**
     * Display a listing of workstation types.
     */
    public function index(Request $request)
    {
        $query = WorkstationType::withCount(['workstations', 'tools'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $workstationTypes = $query->paginate(25)->withQueryString();

        return view('admin.workstation-types.index', compact('workstationTypes'));
    }

    /**
     * Show the form for creating a new workstation type.
     */
    public function create()
    {
        return view('admin.workstation-types.create');
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
        return view('admin.workstation-types.edit', compact('workstationType'));
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
