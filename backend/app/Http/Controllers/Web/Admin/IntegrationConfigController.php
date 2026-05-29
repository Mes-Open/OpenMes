<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IntegrationConfigController extends Controller
{
    public function index()
    {
        $counts = IntegrationConfig::withCount('materialSources')
            ->get(['id'])
            ->mapWithKeys(fn ($r) => [$r->id => $r->material_sources_count]);

        return Inertia::render('admin/integrations/Index', [
            'counts' => $counts,
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/integrations/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'system_type' => 'required|string|max:50|unique:integration_configs,system_type',
            'system_name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        IntegrationConfig::create($validated);

        return redirect()->route('admin.integrations.index')
            ->with('success', 'Integration created successfully.');
    }

    public function edit(IntegrationConfig $integration)
    {
        return Inertia::render('admin/integrations/Edit', [
            'integration' => $integration->only('id', 'system_type', 'system_name', 'is_active'),
        ]);
    }

    public function update(Request $request, IntegrationConfig $integration)
    {
        $validated = $request->validate([
            'system_type' => 'required|string|max:50|unique:integration_configs,system_type,'.$integration->id,
            'system_name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $integration->update($validated);

        return redirect()->route('admin.integrations.index')
            ->with('success', 'Integration updated successfully.');
    }

    public function destroy(IntegrationConfig $integration)
    {
        if ($integration->materialSources()->exists()) {
            return redirect()->route('admin.integrations.index')
                ->with('error', 'Cannot delete integration with linked materials. Deactivate it instead.');
        }

        $integration->delete();

        return redirect()->route('admin.integrations.index')
            ->with('success', 'Integration deleted successfully.');
    }
}
