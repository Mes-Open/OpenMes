<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FactoryController extends Controller
{
    /**
     * Display a listing of factories. Rows live-sync via the `factories`
     * shape; division counts come as a prop.
     */
    public function index()
    {
        $counts = Factory::withCount('divisions')
            ->get(['id'])
            ->mapWithKeys(fn ($r) => [$r->id => $r->divisions_count]);

        return Inertia::render('admin/factories/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new factory.
     */
    public function create()
    {
        return Inertia::render('admin/factories/Create');
    }

    /**
     * Store a newly created factory.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:factories',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Factory::create($validated);

        return redirect()->route('admin.factories.index')
            ->with('success', 'Factory created successfully.');
    }

    /**
     * Display the specified factory.
     */
    public function show(Factory $factory)
    {
        $factory->load(['divisions' => function ($q) {
            $q->withCount('crews')->orderBy('name');
        }]);

        return Inertia::render('admin/factories/Show', [
            'factory' => array_merge(
                $factory->only('id', 'code', 'name', 'description', 'is_active'),
                [
                    'divisions' => $factory->divisions->map(fn ($d) => array_merge(
                        $d->only('id', 'code', 'name', 'is_active'),
                        ['crews_count' => $d->crews_count],
                    )),
                ],
            ),
        ]);
    }

    /**
     * Show the form for editing a factory.
     */
    public function edit(Factory $factory)
    {
        return Inertia::render('admin/factories/Edit', [
            'factory' => $factory->only('id', 'code', 'name', 'description', 'is_active'),
        ]);
    }

    /**
     * Update the specified factory.
     */
    public function update(Request $request, Factory $factory)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:factories,code,' . $factory->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $factory->update($validated);

        return redirect()->route('admin.factories.index')
            ->with('success', 'Factory updated successfully.');
    }

    /**
     * Remove the specified factory.
     */
    public function destroy(Factory $factory)
    {
        if ($factory->divisions()->count() > 0) {
            return redirect()->route('admin.factories.index')
                ->with('error', 'Cannot delete factory with existing divisions. Deactivate it instead.');
        }

        $factory->delete();

        return redirect()->route('admin.factories.index')
            ->with('success', 'Factory deleted successfully.');
    }

    /**
     * Toggle factory active status.
     */
    public function toggleActive(Factory $factory)
    {
        $factory->update(['is_active' => ! $factory->is_active]);

        $status = $factory->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.factories.index')
            ->with('success', "Factory {$status} successfully.");
    }
}
