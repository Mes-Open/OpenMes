<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FactoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Factory::class);

        $query = Factory::query()->withCount('divisions');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Factory $factory): JsonResponse
    {
        $this->authorize('view', $factory);
        $factory->loadCount('divisions');
        $factory->load('divisions');
        return response()->json(['data' => $factory]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Factory::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:factories,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $f = Factory::create($data);
        return response()->json(['message' => 'Factory created', 'data' => $f], 201);
    }

    public function update(Request $request, Factory $factory): JsonResponse
    {
        $this->authorize('update', $factory);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('factories', 'code')->ignore($factory->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $factory->update($data);
        return response()->json(['message' => 'Factory updated', 'data' => $factory->fresh()]);
    }

    public function destroy(Factory $factory): JsonResponse
    {
        $this->authorize('delete', $factory);
        if ($factory->divisions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete factory with divisions. Deactivate it instead.',
            ], 422);
        }
        $factory->delete();
        return response()->json(['message' => 'Factory deleted']);
    }

    public function toggleActive(Factory $factory): JsonResponse
    {
        $this->authorize('update', $factory);
        $factory->update(['is_active' => !$factory->is_active]);
        return response()->json([
            'message' => $factory->is_active ? 'Activated' : 'Deactivated',
            'data' => $factory,
        ]);
    }
}
