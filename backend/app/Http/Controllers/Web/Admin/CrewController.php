<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\Division;
use App\Models\Line;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CrewController extends Controller
{
    use StaysOnList;

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
            // Option lists for the list page's create/edit drawer. Optional, so the
            // queries only run once someone opens it — most visits never do.
            'divisions' => Inertia::optional(fn () => Division::active()->orderBy('name')->get(['id', 'name'])),
            'users' => Inertia::optional(fn () => User::orderBy('name')->get(['id', 'name'])),
            'lines' => Inertia::optional(fn () => Line::where('is_active', true)->orderBy('name')->get(['id', 'name'])),
            // Line assignments are a pivot, so they're absent from the `crews`
            // collection the list rows come from — the drawer can't read them off
            // the record the way it reads every other field.
            'crewLines' => Inertia::optional(fn () => DB::table('crew_line')
                ->get(['crew_id', 'line_id'])
                ->groupBy('crew_id')
                ->map(fn ($rows) => $rows->pluck('line_id')->all())),
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

        return $this->saved($request, redirect()->route('admin.crews.index'), 'Crew created successfully.');
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
        // Only touch the pivot when the caller actually sent it. Defaulting to []
        // here would let any partial update — a form that doesn't carry the field —
        // silently detach every line the crew is assigned to.
        if ($request->has('line_ids')) {
            $crew->lines()->sync($request->input('line_ids', []));
        }

        return $this->saved($request, redirect()->route('admin.crews.index'), 'Crew updated successfully.');
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
