<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceEventController extends Controller
{
    private const TYPES = [
        MaintenanceEvent::TYPE_PLANNED,
        MaintenanceEvent::TYPE_CORRECTIVE,
        MaintenanceEvent::TYPE_INSPECTION,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceEvent::query()->with(['tool', 'line', 'workstation', 'costSource']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($toolId = $request->query('tool_id')) {
            $query->where('tool_id', $toolId);
        }
        if ($lineId = $request->query('line_id')) {
            $query->where('line_id', $lineId);
        }
        if ($from = $request->query('from')) {
            $query->where('scheduled_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('scheduled_at', '<=', $to);
        }

        $perPage = (int) $request->query('per_page', 30);
        $perPage = max(1, min($perPage, 100));
        $page = $query->orderByDesc('scheduled_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        $this->authorize('view', $maintenanceEvent);
        $maintenanceEvent->load(['tool', 'line', 'workstation', 'costSource']);
        return response()->json(['data' => $maintenanceEvent]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MaintenanceEvent::class);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_type' => ['required', Rule::in(self::TYPES)],
            'tool_id' => ['nullable', 'integer', 'exists:tools,id'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'cost_source_id' => ['nullable', 'integer', 'exists:cost_sources,id'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);
        $data['status'] = MaintenanceEvent::STATUS_PENDING;
        $event = MaintenanceEvent::create($data);
        return response()->json([
            'message' => 'Maintenance event created',
            'data' => $event->load(['tool', 'line', 'workstation', 'costSource']),
        ], 201);
    }

    public function update(Request $request, MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        $this->authorize('update', $maintenanceEvent);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'event_type' => ['sometimes', Rule::in(self::TYPES)],
            'tool_id' => ['sometimes', 'nullable', 'integer', 'exists:tools,id'],
            'line_id' => ['sometimes', 'nullable', 'integer', 'exists:lines,id'],
            'workstation_id' => ['sometimes', 'nullable', 'integer', 'exists:workstations,id'],
            'cost_source_id' => ['sometimes', 'nullable', 'integer', 'exists:cost_sources,id'],
            'assigned_to_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
            'resolution_notes' => ['sometimes', 'nullable', 'string'],
            'actual_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);
        $maintenanceEvent->update($data);
        return response()->json([
            'message' => 'Maintenance event updated',
            'data' => $maintenanceEvent->fresh(['tool', 'line', 'workstation', 'costSource']),
        ]);
    }

    public function destroy(MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        $this->authorize('delete', $maintenanceEvent);
        if ($maintenanceEvent->status !== MaintenanceEvent::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only PENDING events can be deleted.',
            ], 422);
        }
        $maintenanceEvent->delete();
        return response()->json(['message' => 'Maintenance event deleted']);
    }

    // ── Status transitions ──────────────────────────────────────────────────

    public function start(MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        $this->authorize('transition', $maintenanceEvent);
        if ($maintenanceEvent->status !== MaintenanceEvent::STATUS_PENDING) {
            return response()->json(['message' => 'Only PENDING events can be started.'], 422);
        }
        $maintenanceEvent->update([
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        return response()->json([
            'message' => 'Started',
            'data' => $maintenanceEvent->fresh(['tool', 'line']),
        ]);
    }

    public function complete(Request $request, MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        $this->authorize('transition', $maintenanceEvent);
        if ($maintenanceEvent->status !== MaintenanceEvent::STATUS_IN_PROGRESS) {
            return response()->json(['message' => 'Only IN_PROGRESS events can be completed.'], 422);
        }
        $data = $request->validate([
            'resolution_notes' => ['nullable', 'string'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);
        $maintenanceEvent->update(array_merge($data, [
            'status' => MaintenanceEvent::STATUS_COMPLETED,
            'completed_at' => now(),
        ]));
        return response()->json([
            'message' => 'Completed',
            'data' => $maintenanceEvent->fresh(['tool', 'line']),
        ]);
    }

    public function cancel(MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        $this->authorize('transition', $maintenanceEvent);
        if (in_array($maintenanceEvent->status, [MaintenanceEvent::STATUS_COMPLETED, MaintenanceEvent::STATUS_CANCELLED], true)) {
            return response()->json(['message' => 'Event is already in a terminal state.'], 422);
        }
        $maintenanceEvent->update(['status' => MaintenanceEvent::STATUS_CANCELLED]);
        return response()->json([
            'message' => 'Cancelled',
            'data' => $maintenanceEvent->fresh(),
        ]);
    }
}
