<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Models\WorkstationType;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    /**
     * Display a listing of tools.
     */
    public function index(Request $request)
    {
        $query = Tool::with('workstationType')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($workstationTypeId = $request->input('workstation_type_id')) {
            $query->where('workstation_type_id', $workstationTypeId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $tools            = $query->paginate(25)->withQueryString();
        $workstationTypes = WorkstationType::orderBy('name')->get();

        return view('admin.tools.index', compact('tools', 'workstationTypes'));
    }

    /**
     * Show the form for creating a new tool.
     */
    public function create()
    {
        $workstationTypes = WorkstationType::active()->orderBy('name')->get();

        return view('admin.tools.create', compact('workstationTypes'));
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
        $workstationTypes = WorkstationType::active()->orderBy('name')->get();

        return view('admin.tools.edit', compact('tool', 'workstationTypes'));
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
