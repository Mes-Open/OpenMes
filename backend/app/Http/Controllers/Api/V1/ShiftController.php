<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shift::query()->with('line');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($lineId = $request->query('line_id')) {
            $query->where('line_id', $lineId);
        }
        return response()->json(['data' => $query->orderBy('start_time')->get()]);
    }

    public function show(Shift $shift): JsonResponse
    {
        $this->authorize('view', $shift);
        $shift->load('line');
        return response()->json(['data' => $shift]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Shift::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $s = Shift::create($data);
        return response()->json(['message' => 'Shift created', 'data' => $s->load('line')], 201);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'days_of_week' => ['sometimes', 'nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'line_id' => ['sometimes', 'nullable', 'integer', 'exists:lines,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $shift->update($data);
        return response()->json(['message' => 'Shift updated', 'data' => $shift->fresh(['line'])]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('delete', $shift);
        $shift->delete();
        return response()->json(['message' => 'Shift deleted']);
    }
}
