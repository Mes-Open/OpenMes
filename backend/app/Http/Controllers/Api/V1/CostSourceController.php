<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CostSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CostSource::query();
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(CostSource $costSource): JsonResponse
    {
        $this->authorize('view', $costSource);
        return response()->json(['data' => $costSource]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CostSource::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:cost_sources,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $cs = CostSource::create($data);
        return response()->json(['message' => 'Cost source created', 'data' => $cs], 201);
    }

    public function update(Request $request, CostSource $costSource): JsonResponse
    {
        $this->authorize('update', $costSource);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('cost_sources', 'code')->ignore($costSource->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $costSource->update($data);
        return response()->json(['message' => 'Cost source updated', 'data' => $costSource->fresh()]);
    }

    public function destroy(CostSource $costSource): JsonResponse
    {
        $this->authorize('delete', $costSource);
        if ($costSource->additionalCosts()->exists() || $costSource->maintenanceEvents()->exists()) {
            return response()->json(['message' => 'Cannot delete cost source referenced by costs or maintenance events.'], 422);
        }
        $costSource->delete();
        return response()->json(['message' => 'Cost source deleted']);
    }

    public function toggleActive(CostSource $costSource): JsonResponse
    {
        $this->authorize('update', $costSource);
        $costSource->update(['is_active' => !$costSource->is_active]);
        return response()->json(['message' => $costSource->is_active ? 'Activated' : 'Deactivated', 'data' => $costSource]);
    }
}
