<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnomalyReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnomalyReasonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AnomalyReason::query();
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(AnomalyReason $anomalyReason): JsonResponse
    {
        $this->authorize('view', $anomalyReason);
        return response()->json(['data' => $anomalyReason]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AnomalyReason::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:anomaly_reasons,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $a = AnomalyReason::create($data);
        return response()->json(['message' => 'Anomaly reason created', 'data' => $a], 201);
    }

    public function update(Request $request, AnomalyReason $anomalyReason): JsonResponse
    {
        $this->authorize('update', $anomalyReason);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('anomaly_reasons', 'code')->ignore($anomalyReason->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $anomalyReason->update($data);
        return response()->json(['message' => 'Anomaly reason updated', 'data' => $anomalyReason->fresh()]);
    }

    public function destroy(AnomalyReason $anomalyReason): JsonResponse
    {
        $this->authorize('delete', $anomalyReason);
        if ($anomalyReason->anomalies()->exists()) {
            return response()->json(['message' => 'Cannot delete reason referenced by production anomalies.'], 422);
        }
        $anomalyReason->delete();
        return response()->json(['message' => 'Anomaly reason deleted']);
    }
}
