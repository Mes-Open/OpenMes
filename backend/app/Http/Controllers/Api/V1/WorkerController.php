<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Worker::class);

        $query = Worker::query()->with(['crew', 'wageGroup', 'workstation']);

        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($crewId = $request->query('crew_id')) {
            $query->where('crew_id', $crewId);
        }
        if ($wgId = $request->query('wage_group_id')) {
            $query->where('wage_group_id', $wgId);
        }
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }

        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $page = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Worker $worker): JsonResponse
    {
        $this->authorize('view', $worker);
        $worker->load(['crew', 'wageGroup', 'workstation', 'skills', 'user']);
        return response()->json(['data' => $worker]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Worker::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:workers,code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'crew_id' => ['nullable', 'integer', 'exists:crews,id'],
            'wage_group_id' => ['nullable', 'integer', 'exists:wage_groups,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'is_active' => ['nullable', 'boolean'],
            'skills' => ['nullable', 'array'],
            'skills.*.id' => ['required', 'integer', 'exists:skills,id'],
            'skills.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $worker = DB::transaction(function () use ($data) {
            $worker = Worker::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'crew_id' => $data['crew_id'] ?? null,
                'wage_group_id' => $data['wage_group_id'] ?? null,
                'workstation_id' => $data['workstation_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['skills'])) {
                $skillsSync = collect($data['skills'])
                    ->mapWithKeys(fn($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
                    ->toArray();
                $worker->skills()->sync($skillsSync);
            }

            return $worker;
        });

        return response()->json([
            'message' => 'Worker created',
            'data' => $worker->load(['crew', 'wageGroup', 'workstation', 'skills']),
        ], 201);
    }

    public function update(Request $request, Worker $worker): JsonResponse
    {
        $this->authorize('update', $worker);

        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('workers', 'code')->ignore($worker->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'crew_id' => ['sometimes', 'nullable', 'integer', 'exists:crews,id'],
            'wage_group_id' => ['sometimes', 'nullable', 'integer', 'exists:wage_groups,id'],
            'workstation_id' => ['sometimes', 'nullable', 'integer', 'exists:workstations,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $worker->update($data);

        return response()->json([
            'message' => 'Worker updated',
            'data' => $worker->fresh(['crew', 'wageGroup', 'workstation', 'skills']),
        ]);
    }

    public function destroy(Worker $worker): JsonResponse
    {
        $this->authorize('delete', $worker);

        if ($worker->user()->exists()) {
            return response()->json([
                'message' => 'Cannot delete worker linked to a user account. Unlink the user first.',
            ], 422);
        }

        $worker->delete();
        return response()->json(['message' => 'Worker deleted']);
    }

    public function syncSkills(Request $request, Worker $worker): JsonResponse
    {
        $this->authorize('manageSkills', $worker);

        $data = $request->validate([
            'skills' => ['required', 'array'],
            'skills.*.id' => ['required', 'integer', 'exists:skills,id'],
            'skills.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $sync = collect($data['skills'])
            ->mapWithKeys(fn($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
            ->toArray();
        $worker->skills()->sync($sync);

        return response()->json([
            'message' => 'Skills updated',
            'data' => $worker->fresh()->skills,
        ]);
    }
}
