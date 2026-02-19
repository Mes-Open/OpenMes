<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\Skill;
use App\Models\WageGroup;
use App\Models\Worker;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
    /**
     * Display a listing of workers.
     */
    public function index(Request $request)
    {
        $query = Worker::with(['crew', 'wageGroup'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($crewId = $request->input('crew_id')) {
            $query->where('crew_id', $crewId);
        }

        if ($wageGroupId = $request->input('wage_group_id')) {
            $query->where('wage_group_id', $wageGroupId);
        }

        $workers    = $query->paginate(25)->withQueryString();
        $crews      = Crew::orderBy('name')->get();
        $wageGroups = WageGroup::orderBy('name')->get();

        return view('admin.workers.index', compact('workers', 'crews', 'wageGroups'));
    }

    /**
     * Show the form for creating a new worker.
     */
    public function create()
    {
        $crews      = Crew::active()->orderBy('name')->get();
        $wageGroups = WageGroup::active()->orderBy('name')->get();
        $skills     = Skill::orderBy('name')->get();

        return view('admin.workers.create', compact('crews', 'wageGroups', 'skills'));
    }

    /**
     * Store a newly created worker.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:50|unique:workers',
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'crew_id'       => 'nullable|exists:crews,id',
            'wage_group_id' => 'nullable|exists:wage_groups,id',
            'is_active'     => 'boolean',
            'skills'        => 'nullable|array',
            'skills.*.id'   => 'required|exists:skills,id',
            'skills.*.level'=> 'nullable|integer|min:1|max:5',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $worker = Worker::create($validated);

        $worker->skills()->sync(
            collect($request->input('skills', []))->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
        );

        return redirect()->route('admin.workers.index')
            ->with('success', 'Worker created successfully.');
    }

    /**
     * Show the form for editing a worker.
     */
    public function edit(Worker $worker)
    {
        $worker->load('skills');
        $crews      = Crew::active()->orderBy('name')->get();
        $wageGroups = WageGroup::active()->orderBy('name')->get();
        $skills     = Skill::orderBy('name')->get();

        return view('admin.workers.edit', compact('worker', 'crews', 'wageGroups', 'skills'));
    }

    /**
     * Update the specified worker.
     */
    public function update(Request $request, Worker $worker)
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:50|unique:workers,code,' . $worker->id,
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'crew_id'       => 'nullable|exists:crews,id',
            'wage_group_id' => 'nullable|exists:wage_groups,id',
            'is_active'     => 'boolean',
            'skills'        => 'nullable|array',
            'skills.*.id'   => 'required|exists:skills,id',
            'skills.*.level'=> 'nullable|integer|min:1|max:5',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $worker->update($validated);

        $worker->skills()->sync(
            collect($request->input('skills', []))->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
        );

        return redirect()->route('admin.workers.index')
            ->with('success', 'Worker updated successfully.');
    }

    /**
     * Remove the specified worker.
     */
    public function destroy(Worker $worker)
    {
        $worker->skills()->detach();
        $worker->delete();

        return redirect()->route('admin.workers.index')
            ->with('success', 'Worker deleted successfully.');
    }

    /**
     * Toggle worker active status.
     */
    public function toggleActive(Worker $worker)
    {
        $worker->update(['is_active' => ! $worker->is_active]);

        $status = $worker->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.workers.index')
            ->with('success', "Worker {$status} successfully.");
    }
}
