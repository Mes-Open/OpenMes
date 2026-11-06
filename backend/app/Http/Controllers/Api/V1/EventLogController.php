<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventLogController extends Controller
{
    /**
     * Get event logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'nullable|string',
            'entity_type' => 'nullable|string',
            'entity_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = EventLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event_type')) {
            $query->eventType($request->event_type);
        }

        if ($request->filled('entity_type') && $request->filled('entity_id')) {
            $query->entity($request->entity_type, $request->entity_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $perPage = $request->input('per_page', 20);
        $eventLogs = $query->paginate($perPage);

        return response()->json([
            'data' => $eventLogs->items(),
            'meta' => [
                'current_page' => $eventLogs->currentPage(),
                'per_page' => $eventLogs->perPage(),
                'total' => $eventLogs->total(),
                'last_page' => $eventLogs->lastPage(),
            ],
        ]);
    }

    /**
     * Get event logs for a specific entity (e.g., work order timeline).
     */
    public function entity(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
        ]);

        $eventLogs = EventLog::entity($request->entity_type, $request->entity_id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $eventLogs,
        ]);
    }
}
