<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ToolController extends Controller
{
    private const STATUSES = [
        Tool::STATUS_AVAILABLE,
        Tool::STATUS_IN_USE,
        Tool::STATUS_MAINTENANCE,
        Tool::STATUS_RETIRED,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Tool::query()->with('workstationType');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($wtId = $request->query('workstation_type_id')) {
            $query->where('workstation_type_id', $wtId);
        }
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
            });
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Tool $tool): JsonResponse
    {
        $this->authorize('view', $tool);
        $tool->load(['workstationType', 'maintenanceEvents' => fn($q) => $q->orderByDesc('scheduled_at')->limit(10)]);
        return response()->json(['data' => $tool]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Tool::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:tools,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'workstation_type_id' => ['nullable', 'integer', 'exists:workstation_types,id'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'next_service_at' => ['nullable', 'date'],
        ]);
        $data['status'] = $data['status'] ?? Tool::STATUS_AVAILABLE;
        $tool = Tool::create($data);
        return response()->json(['message' => 'Tool created', 'data' => $tool->load('workstationType')], 201);
    }

    public function update(Request $request, Tool $tool): JsonResponse
    {
        $this->authorize('update', $tool);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('tools', 'code')->ignore($tool->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'workstation_type_id' => ['sometimes', 'nullable', 'integer', 'exists:workstation_types,id'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'next_service_at' => ['sometimes', 'nullable', 'date'],
        ]);
        $tool->update($data);
        return response()->json(['message' => 'Tool updated', 'data' => $tool->fresh(['workstationType'])]);
    }

    public function destroy(Tool $tool): JsonResponse
    {
        $this->authorize('delete', $tool);
        if ($tool->maintenanceEvents()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete tool with open maintenance events.',
            ], 422);
        }
        $tool->delete();
        return response()->json(['message' => 'Tool deleted']);
    }

    public function transitionStatus(Request $request, Tool $tool): JsonResponse
    {
        $this->authorize('update', $tool);
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);
        $tool->update(['status' => $data['status']]);
        return response()->json([
            'message' => "Tool status set to {$data['status']}",
            'data' => $tool->fresh(),
        ]);
    }
}
