<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Services\Maintenance\GenerateMaintenanceEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mirror of Web/Admin/MaintenanceScheduleController — recurring schedules
 * that auto-generate MaintenanceEvents via the GenerateMaintenanceEvents
 * service. Admin-only at the route layer (see routes/api.php).
 */
class MaintenanceScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceSchedule::query()
            ->with(['tool', 'line', 'workstation', 'assignedTo'])
            ->orderBy('is_active', 'desc')
            ->orderBy('next_due_at');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($frequency = $request->query('frequency')) {
            $query->where('frequency', $frequency);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function show(MaintenanceSchedule $maintenanceSchedule): JsonResponse
    {
        $maintenanceSchedule->load(['tool', 'line', 'workstation', 'assignedTo', 'costSource']);
        return response()->json(['data' => $maintenanceSchedule]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $validated['created_by_id'] = $request->user()?->id;
        $validated['is_active']     = $request->boolean('is_active', true);

        $schedule = MaintenanceSchedule::create($validated);
        $schedule->load(['tool', 'line', 'workstation', 'assignedTo']);
        return response()->json(['data' => $schedule], 201);
    }

    public function update(Request $request, MaintenanceSchedule $maintenanceSchedule): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $validated['is_active'] = $request->boolean('is_active', false);

        $maintenanceSchedule->update($validated);
        $maintenanceSchedule->load(['tool', 'line', 'workstation', 'assignedTo']);
        return response()->json(['data' => $maintenanceSchedule]);
    }

    public function destroy(MaintenanceSchedule $maintenanceSchedule): JsonResponse
    {
        $maintenanceSchedule->delete();
        return response()->json(['message' => 'Maintenance schedule deleted']);
    }

    /**
     * Force-generate one event for this schedule immediately. Idempotent via
     * a (schedule_id, scheduled_at) existence check — calling twice in the
     * same cycle returns 409 instead of creating a duplicate.
     */
    public function generateNow(MaintenanceSchedule $maintenanceSchedule, GenerateMaintenanceEvents $service): JsonResponse
    {
        if (! $maintenanceSchedule->is_active) {
            return response()->json(['message' => 'Cannot generate an event from an inactive schedule.'], 422);
        }

        $exists = MaintenanceEvent::query()
            ->where('schedule_id', $maintenanceSchedule->id)
            ->where('scheduled_at', $maintenanceSchedule->next_due_at)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'An event for this cycle already exists.'], 409);
        }

        if ($maintenanceSchedule->next_due_at && $maintenanceSchedule->next_due_at->isFuture()) {
            $maintenanceSchedule->forceFill(['next_due_at' => now()])->save();
        }

        $created = $service->run();
        return response()->json(['data' => ['generated' => $created]]);
    }

    /** Shared validation — mirrors the web controller exactly. */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'tool_id'          => 'nullable|required_without_all:line_id,workstation_id|exists:tools,id',
            'line_id'          => 'nullable|required_without_all:tool_id,workstation_id|exists:lines,id',
            'workstation_id'   => 'nullable|required_without_all:tool_id,line_id|exists:workstations,id',
            'event_type'       => 'required|string|in:planned,corrective,inspection',
            'assigned_to_id'   => 'nullable|exists:users,id',
            'cost_source_id'   => 'nullable|exists:cost_sources,id',
            'frequency'        => 'required|string|in:' . implode(',', MaintenanceSchedule::FREQUENCIES),
            'interval_value'   => 'required|integer|min:1',
            'preferred_time'   => 'nullable|date_format:H:i',
            'lead_time_days'   => 'nullable|integer|min:0|max:30',
            'next_due_at'      => 'required|date',
        ], [
            'tool_id.required_without_all'        => 'Select at least one of: Tool, Line, or Workstation.',
            'line_id.required_without_all'        => 'Select at least one of: Tool, Line, or Workstation.',
            'workstation_id.required_without_all' => 'Select at least one of: Tool, Line, or Workstation.',
        ]);
    }
}
