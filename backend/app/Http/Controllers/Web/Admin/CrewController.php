<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\Division;
use App\Models\Line;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class CrewController extends Controller
{
    /**
     * Display a listing of crews.
     */
    public function index(Request $request)
    {
        $counts = Crew::withCount('workers')->get(['id'])->mapWithKeys(fn ($c) => [$c->id => $c->workers_count]);
        $divisionNames = Division::pluck('name', 'id');
        $leaderNames = User::pluck('name', 'id');

        return Inertia::render('admin/crews/Index', [
            'counts' => $counts,
            'divisionNames' => $divisionNames,
            'leaderNames' => $leaderNames,
        ]);
    }

    /**
     * Show the form for creating a new crew.
     */
    public function create()
    {
        $divisions = Division::active()->orderBy('name')->get(['id', 'name']);
        $users = User::orderBy('name')->get(['id', 'name']);
        $lines = Line::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/crews/Create', [
            'divisions' => $divisions,
            'users' => $users,
            'lines' => $lines,
        ]);
    }

    /**
     * Store a newly created crew.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:crews',
            'name' => 'required|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'leader_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'line_ids' => 'nullable|array',
            'line_ids.*' => 'integer|exists:lines,id',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $crew = Crew::create(Arr::except($validated, 'line_ids'));
        $crew->lines()->sync($request->input('line_ids', []));

        return redirect()->route('admin.crews.index')
            ->with('success', 'Crew created successfully.');
    }

    /**
     * Show the form for editing a crew.
     */
    public function edit(Crew $crew)
    {
        $divisions = Division::active()->orderBy('name')->get(['id', 'name']);
        $users = User::orderBy('name')->get(['id', 'name']);
        $lines = Line::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/crews/Edit', [
            'crew' => array_merge(
                $crew->only('id', 'code', 'name', 'leader_id', 'division_id', 'description', 'is_active'),
                ['line_ids' => $crew->lines()->pluck('lines.id')->all()],
            ),
            'divisions' => $divisions,
            'users' => $users,
            'lines' => $lines,
        ]);
    }

    /**
     * Update the specified crew.
     */
    public function update(Request $request, Crew $crew)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:crews,code,'.$crew->id,
            'name' => 'required|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'leader_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'line_ids' => 'nullable|array',
            'line_ids.*' => 'integer|exists:lines,id',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $crew->update(Arr::except($validated, 'line_ids'));
        $crew->lines()->sync($request->input('line_ids', []));

        return redirect()->route('admin.crews.index')
            ->with('success', 'Crew updated successfully.');
    }

    /**
     * Remove the specified crew.
     */
    public function destroy(Crew $crew)
    {
        if ($crew->workers()->count() > 0) {
            return redirect()->route('admin.crews.index')
                ->with('error', 'Cannot delete crew with assigned workers. Deactivate it instead.');
        }

        $crew->delete();

        return redirect()->route('admin.crews.index')
            ->with('success', 'Crew deleted successfully.');
    }
}
