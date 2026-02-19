<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    /**
     * Display a listing of skills.
     */
    public function index(Request $request)
    {
        $query = Skill::withCount('workers')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $skills = $query->paginate(25)->withQueryString();

        return view('admin.skills.index', compact('skills'));
    }

    /**
     * Show the form for creating a new skill.
     */
    public function create()
    {
        return view('admin.skills.create');
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
        return view('admin.skills.edit', compact('skill'));
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
