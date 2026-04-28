<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subassembly;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubassemblyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subassembly::class);

        $query = Subassembly::query()->with('productType');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($ptId = $request->query('product_type_id')) {
            $query->where('product_type_id', $ptId);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Subassembly $subassembly): JsonResponse
    {
        $this->authorize('view', $subassembly);
        $subassembly->load('productType');
        return response()->json(['data' => $subassembly]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Subassembly::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:subassemblies,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'product_type_id' => ['nullable', 'integer', 'exists:product_types,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $s = Subassembly::create($data);
        return response()->json(['message' => 'Subassembly created', 'data' => $s->load('productType')], 201);
    }

    public function update(Request $request, Subassembly $subassembly): JsonResponse
    {
        $this->authorize('update', $subassembly);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('subassemblies', 'code')->ignore($subassembly->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'product_type_id' => ['sometimes', 'nullable', 'integer', 'exists:product_types,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $subassembly->update($data);
        return response()->json(['message' => 'Subassembly updated', 'data' => $subassembly->fresh()]);
    }

    public function destroy(Subassembly $subassembly): JsonResponse
    {
        $this->authorize('delete', $subassembly);
        $subassembly->delete();
        return response()->json(['message' => 'Subassembly deleted']);
    }
}
