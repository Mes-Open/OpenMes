<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SkillController extends Controller
{
    /**
     * Display a listing of skills. Rows live-sync via the `skills` shape; only
     * the worker counts (cross-table) come as a prop.
     */
    public function index()
    {
        $counts = Skill::withCount('workers')
            ->get(['id'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->workers_count]);

        return Inertia::render('admin/skills/Index', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show the form for creating a new skill.
     */
    public function create()
    {
        return Inertia::render('admin/skills/Create');
    }

    /**
     * Store a newly created skill.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:skills',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        Skill::create($validated);

        return redirect()->route('admin.skills.index')
            ->with('success', 'Skill created successfully.');
    }

    /**
     * Show the form for editing a skill.
     */
    public function edit(Skill $skill)
    {
        return Inertia::render('admin/skills/Edit', [
            'skill' => $skill->only('id', 'code', 'name', 'description'),
        ]);
    }

    /**
     * Update the specified skill.
     */
    public function update(Request $request, Skill $skill)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:skills,code,' . $skill->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $skill->update($validated);

        return redirect()->route('admin.skills.index')
            ->with('success', 'Skill updated successfully.');
    }

    /**
     * Remove the specified skill.
     */
    public function destroy(Skill $skill)
    {
        if ($skill->workers()->count() > 0) {
            return redirect()->route('admin.skills.index')
                ->with('error', 'Cannot delete skill assigned to workers. Remove it from all workers first.');
        }

        $skill->delete();

        return redirect()->route('admin.skills.index')
            ->with('success', 'Skill deleted successfully.');
    }
}
