<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WageGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WageGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WageGroup::query()->withCount('workers');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(WageGroup $wageGroup): JsonResponse
    {
        $this->authorize('view', $wageGroup);
        $wageGroup->loadCount('workers');
        return response()->json(['data' => $wageGroup]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WageGroup::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:wage_groups,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $wg = WageGroup::create($data);
        return response()->json(['message' => 'Wage group created', 'data' => $wg], 201);
    }

    public function update(Request $request, WageGroup $wageGroup): JsonResponse
    {
        $this->authorize('update', $wageGroup);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('wage_groups', 'code')->ignore($wageGroup->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'base_hourly_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $wageGroup->update($data);
        return response()->json(['message' => 'Wage group updated', 'data' => $wageGroup->fresh()]);
    }

    public function destroy(WageGroup $wageGroup): JsonResponse
    {
        $this->authorize('delete', $wageGroup);
        if ($wageGroup->workers()->exists()) {
            return response()->json([
                'message' => 'Cannot delete wage group with assigned workers.',
            ], 422);
        }
        $wageGroup->delete();
        return response()->json(['message' => 'Wage group deleted']);
    }

    public function toggleActive(WageGroup $wageGroup): JsonResponse
    {
        $this->authorize('update', $wageGroup);
        $wageGroup->update(['is_active' => !$wageGroup->is_active]);
        return response()->json([
            'message' => $wageGroup->is_active ? 'Activated' : 'Deactivated',
            'data' => $wageGroup,
        ]);
    }
}
