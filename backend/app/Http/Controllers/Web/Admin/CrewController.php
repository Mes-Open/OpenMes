<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;

class CrewController extends Controller
{
    /**
     * Display a listing of crews.
     */
    public function index(Request $request)
    {
        $query = Crew::with(['division', 'leader'])
            ->withCount('workers')
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($divisionId = $request->input('division_id')) {
            $query->where('division_id', $divisionId);
        }

        $crews     = $query->paginate(25)->withQueryString();
        $divisions = Division::orderBy('name')->get();

        return view('admin.crews.index', compact('crews', 'divisions'));
    }

    /**
     * Show the form for creating a new crew.
     */
    public function create()
    {
        $divisions = Division::active()->orderBy('name')->get();
        $users     = User::orderBy('name')->get();

        return view('admin.crews.create', compact('divisions', 'users'));
    }

    /**
     * Store a newly created crew.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:crews',
            'name'        => 'required|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'leader_id'   => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Crew::create($validated);

        return redirect()->route('admin.crews.index')
            ->with('success', 'Crew created successfully.');
    }

    /**
     * Show the form for editing a crew.
     */
    public function edit(Crew $crew)
    {
        $divisions = Division::active()->orderBy('name')->get();
        $users     = User::orderBy('name')->get();

        return view('admin.crews.edit', compact('crew', 'divisions', 'users'));
    }

    /**
     * Update the specified crew.
     */
    public function update(Request $request, Crew $crew)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:crews,code,' . $crew->id,
            'name'        => 'required|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'leader_id'   => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $crew->update($validated);

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
