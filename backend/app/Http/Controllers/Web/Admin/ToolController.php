<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Models\WorkstationType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ToolController extends Controller
{
    /**
     * Display a listing of tools. Rows live-sync via the `tools` shape; the
     * workstation-type name map is passed for display.
     */
    public function index()
    {
        return Inertia::render('admin/tools/Index', [
            'workstationTypeNames' => WorkstationType::pluck('name', 'id'),
        ]);
    }

    /**
     * Show the form for creating a new tool.
     */
    public function create()
    {
        return Inertia::render('admin/tools/Create', [
            'workstationTypes' => WorkstationType::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created tool.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                => 'required|string|max:50|unique:tools',
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:2000',
            'workstation_type_id' => 'nullable|exists:workstation_types,id',
            'status'              => 'nullable|string|in:available,in_use,maintenance,retired',
            'next_service_at'     => 'nullable|date',
        ]);

        $validated['status'] = $validated['status'] ?? Tool::STATUS_AVAILABLE;

        Tool::create($validated);

        return redirect()->route('admin.tools.index')
            ->with('success', 'Tool created successfully.');
    }

    /**
     * Show the form for editing a tool.
     */
    public function edit(Tool $tool)
    {
        return Inertia::render('admin/tools/Edit', [
            'tool' => $tool->only('id', 'code', 'name', 'description', 'workstation_type_id', 'status', 'next_service_at'),
            'workstationTypes' => WorkstationType::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified tool.
     */
    public function update(Request $request, Tool $tool)
    {
        $validated = $request->validate([
            'code'                => 'required|string|max:50|unique:tools,code,' . $tool->id,
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:2000',
            'workstation_type_id' => 'nullable|exists:workstation_types,id',
            'status'              => 'nullable|string|in:available,in_use,maintenance,retired',
            'next_service_at'     => 'nullable|date',
        ]);

        $tool->update($validated);

        return redirect()->route('admin.tools.index')
            ->with('success', 'Tool updated successfully.');
    }

    /**
     * Remove the specified tool.
     */
    public function destroy(Tool $tool)
    {
        if ($tool->maintenanceEvents()->count() > 0) {
            return redirect()->route('admin.tools.index')
                ->with('error', 'Cannot delete tool with existing maintenance event records.');
        }

        $tool->delete();

        return redirect()->route('admin.tools.index')
            ->with('success', 'Tool deleted successfully.');
    }
}
