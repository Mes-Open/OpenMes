<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Skill::query()->withCount('workers');
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
            });
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Skill $skill): JsonResponse
    {
        $this->authorize('view', $skill);
        $skill->loadCount('workers');
        return response()->json(['data' => $skill]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Skill::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:skills,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $skill = Skill::create($data);
        return response()->json(['message' => 'Skill created', 'data' => $skill], 201);
    }

    public function update(Request $request, Skill $skill): JsonResponse
    {
        $this->authorize('update', $skill);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('skills', 'code')->ignore($skill->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);
        $skill->update($data);
        return response()->json(['message' => 'Skill updated', 'data' => $skill->fresh()]);
    }

    public function destroy(Skill $skill): JsonResponse
    {
        $this->authorize('delete', $skill);
        if ($skill->workers()->exists()) {
            return response()->json([
                'message' => 'Cannot delete skill assigned to workers.',
            ], 422);
        }
        $skill->delete();
        return response()->json(['message' => 'Skill deleted']);
    }
}
