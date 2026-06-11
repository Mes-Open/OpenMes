<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Models\WorkstationType;
use App\Services\CustomFieldService;
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
            'customFields' => app(CustomFieldService::class)->clientConfig('tool'),
        ]);
    }

    /**
     * Store a newly created tool.
     */
    public function store(Request $request)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validate(array_merge([
            'code'                => 'required|string|max:50|unique:tools',
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:2000',
            'workstation_type_id' => 'nullable|exists:workstation_types,id',
            'status'              => 'nullable|string|in:available,in_use,maintenance,retired',
            'next_service_at'     => 'nullable|date',
        ], $cf->rules('tool')), [], $cf->attributeNames('tool'));

        $validated['status'] = $validated['status'] ?? Tool::STATUS_AVAILABLE;
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'tool') ?: null;
        }

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
            'tool' => $tool->only('id', 'code', 'name', 'description', 'workstation_type_id', 'status', 'next_service_at', 'custom_fields'),
            'workstationTypes' => WorkstationType::active()->orderBy('name')->get(['id', 'name']),
            'customFields' => app(CustomFieldService::class)->clientConfig('tool'),
        ]);
    }

    /**
     * Update the specified tool.
     */
    public function update(Request $request, Tool $tool)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validate(array_merge([
            'code'                => 'required|string|max:50|unique:tools,code,' . $tool->id,
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:2000',
            'workstation_type_id' => 'nullable|exists:workstation_types,id',
            'status'              => 'nullable|string|in:available,in_use,maintenance,retired',
            'next_service_at'     => 'nullable|date',
        ], $cf->rules('tool')), [], $cf->attributeNames('tool'));

        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'tool', $tool->custom_fields) ?: null;
        }

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
