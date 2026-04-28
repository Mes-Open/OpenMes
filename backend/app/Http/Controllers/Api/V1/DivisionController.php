<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DivisionController extends Controller
{
    public function index(Request $request, Factory $factory): JsonResponse
    {
        $query = $factory->divisions()->withCount('lines');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Division $division): JsonResponse
    {
        $this->authorize('view', $division);
        $division->loadCount('lines');
        $division->load('factory');
        return response()->json(['data' => $division]);
    }

    public function store(Request $request, Factory $factory): JsonResponse
    {
        $this->authorize('create', Division::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:divisions,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['factory_id'] = $factory->id;
        $data['is_active'] = $data['is_active'] ?? true;
        $d = Division::create($data);
        return response()->json(['message' => 'Division created', 'data' => $d->load('factory')], 201);
    }

    public function update(Request $request, Division $division): JsonResponse
    {
        $this->authorize('update', $division);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('divisions', 'code')->ignore($division->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $division->update($data);
        return response()->json(['message' => 'Division updated', 'data' => $division->fresh()]);
    }

    public function destroy(Division $division): JsonResponse
    {
        $this->authorize('delete', $division);
        if ($division->lines()->exists()) {
            return response()->json([
                'message' => 'Cannot delete division with lines.',
            ], 422);
        }
        $division->delete();
        return response()->json(['message' => 'Division deleted']);
    }

    public function toggleActive(Division $division): JsonResponse
    {
        $this->authorize('update', $division);
        $division->update(['is_active' => !$division->is_active]);
        return response()->json([
            'message' => $division->is_active ? 'Activated' : 'Deactivated',
            'data' => $division,
        ]);
    }
}
