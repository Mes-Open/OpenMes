<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductTypeRequest;
use App\Http\Requests\Api\V1\UpdateProductTypeRequest;
use App\Models\ProductType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductType::query()->withCount('processTemplates');

        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function show(ProductType $productType): JsonResponse
    {
        $this->authorize('view', $productType);
        $productType->loadCount('processTemplates');
        $productType->load('processTemplates');
        return response()->json(['data' => $productType]);
    }

    public function store(StoreProductTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ProductType::class);
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $pt = ProductType::create($data);
        return response()->json([
            'message' => 'Product type created',
            'data' => $pt,
        ], 201);
    }

    public function update(UpdateProductTypeRequest $request, ProductType $productType): JsonResponse
    {
        $this->authorize('update', $productType);
        $productType->update($request->validated());
        return response()->json([
            'message' => 'Product type updated',
            'data' => $productType->fresh(),
        ]);
    }

    public function destroy(ProductType $productType): JsonResponse
    {
        $this->authorize('delete', $productType);

        if ($productType->workOrders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product type referenced by work orders. Deactivate it instead.',
            ], 422);
        }

        $productType->delete();
        return response()->json(['message' => 'Product type deleted']);
    }

    public function toggleActive(ProductType $productType): JsonResponse
    {
        $this->authorize('update', $productType);
        $productType->update(['is_active' => !$productType->is_active]);
        return response()->json([
            'message' => $productType->is_active ? 'Activated' : 'Deactivated',
            'data' => $productType,
        ]);
    }
}
