<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Crew::class);

        $query = Crew::query()->withCount('workers')->with(['leader', 'division']);
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Crew $crew): JsonResponse
    {
        $this->authorize('view', $crew);
        $crew->loadCount('workers');
        $crew->load(['leader', 'division']);
        return response()->json(['data' => $crew]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Crew::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:crews,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $crew = Crew::create($data);
        return response()->json(['message' => 'Crew created', 'data' => $crew->fresh(['leader', 'division'])], 201);
    }

    public function update(Request $request, Crew $crew): JsonResponse
    {
        $this->authorize('update', $crew);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('crews', 'code')->ignore($crew->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:divisions,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $crew->update($data);
        return response()->json(['message' => 'Crew updated', 'data' => $crew->fresh(['leader', 'division'])]);
    }

    public function destroy(Crew $crew): JsonResponse
    {
        $this->authorize('delete', $crew);
        if ($crew->workers()->exists()) {
            return response()->json([
                'message' => 'Cannot delete crew with assigned workers.',
            ], 422);
        }
        $crew->delete();
        return response()->json(['message' => 'Crew deleted']);
    }

    public function toggleActive(Crew $crew): JsonResponse
    {
        $this->authorize('update', $crew);
        $crew->update(['is_active' => !$crew->is_active]);
        return response()->json([
            'message' => $crew->is_active ? 'Activated' : 'Deactivated',
            'data' => $crew,
        ]);
    }

    public function workers(Crew $crew): JsonResponse
    {
        $this->authorize('view', $crew);
        return response()->json([
            'data' => $crew->workers()->orderBy('name')->get(),
        ]);
    }
}
