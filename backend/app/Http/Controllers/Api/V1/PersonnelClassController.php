<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonnelClass;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonnelClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PersonnelClass::query()
            ->withCount('workers')
            ->orderBy('code');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 30);
        $perPage = max(1, min($perPage, 100));
        $page    = $query->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function show(PersonnelClass $personnelClass): JsonResponse
    {
        $personnelClass->loadCount('workers');

        return response()->json([
            'data' => array_merge($personnelClass->toArray(), [
                'required_skills' => $personnelClass->requiredSkills(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data              = $this->validatePayload($request);
        $data['is_active'] = $data['is_active'] ?? true;

        $personnelClass = PersonnelClass::create($data);

        return response()->json([
            'message' => __('Personnel class created'),
            'data'    => $personnelClass,
        ], 201);
    }

    public function update(Request $request, PersonnelClass $personnelClass): JsonResponse
    {
        $data = $this->validatePayload($request, $personnelClass);
        $personnelClass->update($data);

        return response()->json([
            'message' => __('Personnel class updated'),
            'data'    => $personnelClass->fresh(),
        ]);
    }

    public function destroy(Request $request, PersonnelClass $personnelClass): JsonResponse
    {
        $workerCount = $personnelClass->workers()->count();

        if ($workerCount > 0 && ! $request->boolean('force')) {
            return response()->json([
                'message' => __('Cannot delete — :count worker(s) assigned.', ['count' => $workerCount]),
            ], 422);
        }

        if ($workerCount > 0) {
            Worker::where('personnel_class_id', $personnelClass->id)
                ->update(['personnel_class_id' => null]);
        }

        $personnelClass->delete();

        return response()->json(['message' => __('Personnel class deleted')]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function validatePayload(Request $request, ?PersonnelClass $personnelClass = null): array
    {
        $tenantId         = $request->user()?->tenant_id;
        $personnelClassId = $personnelClass?->id;
        $isUpdate         = $personnelClass !== null;

        $rules = [
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('personnel_classes', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($personnelClassId),
            ],
            'name'                          => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description'                   => ['nullable', 'string'],
            'required_skill_ids'            => ['nullable', 'array'],
            'required_skill_ids.*'          => ['integer', 'exists:skills,id'],
            'default_required_cert_level'   => ['nullable', 'array'],
            'default_required_cert_level.*' => [Rule::in(PersonnelClass::LEVELS)],
            'is_active'                     => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
