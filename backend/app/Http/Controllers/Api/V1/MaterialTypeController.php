<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaterialType;
use Illuminate\Http\JsonResponse;

class MaterialTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => MaterialType::orderBy('name')->get(),
        ]);
    }

    public function show(MaterialType $materialType): JsonResponse
    {
        return response()->json(['data' => $materialType]);
    }
}
