<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\ProductionDowntime;
use App\Services\Production\DowntimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OeeController extends Controller
{
    public function __construct(
        protected DowntimeService $downtimeService,
    ) {}

    /**
     * Get OEE records with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'line_id' => 'nullable|exists:lines,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = OeeRecord::with(['line', 'shift']);

        if ($request->line_id) {
            $query->where('line_id', $request->line_id);
        }
        if ($request->date_from) {
            $query->where('record_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('record_date', '<=', $request->date_to);
        }

        return response()->json([
            'data' => $query->orderByDesc('record_date')->limit(100)->get(),
        ]);
    }

    /**
     * Get OEE for a specific line.
     */
    public function show(Line $line, Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);

        $records = OeeRecord::where('line_id', $line->id)
            ->where('record_date', '>=', today()->subDays($days))
            ->with('shift')
            ->orderByDesc('record_date')
            ->get();

        return response()->json(['data' => $records]);
    }

    /**
     * List downtime reasons.
     */
    public function reasons(): JsonResponse
    {
        return response()->json([
            'data' => DowntimeReason::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Start a downtime event.
     */
    public function startDowntime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'workstation_id' => 'nullable|exists:workstations,id',
            'downtime_reason_id' => 'required|exists:downtime_reasons,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $line = Line::findOrFail($validated['line_id']);

        $downtime = $this->downtimeService->start(
            $line,
            $validated['downtime_reason_id'],
            $request->user(),
            $validated['workstation_id'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Downtime started',
            'data' => $downtime->load('reason'),
        ], 201);
    }

    /**
     * Stop an active downtime event.
     */
    public function stopDowntime(ProductionDowntime $downtime): JsonResponse
    {
        if ($downtime->ended_at) {
            return response()->json(['message' => 'Downtime already stopped.'], 422);
        }

        $this->downtimeService->stop($downtime);

        return response()->json([
            'message' => 'Downtime stopped',
            'data' => $downtime->fresh()->load('reason'),
        ]);
    }

    /**
     * List downtimes with filters.
     */
    public function downtimes(Request $request): JsonResponse
    {
        $query = ProductionDowntime::with(['reason', 'line', 'workstation', 'reportedBy']);

        if ($request->line_id) {
            $query->where('line_id', $request->line_id);
        }
        if ($request->date) {
            $query->whereDate('started_at', $request->date);
        }

        return response()->json([
            'data' => $query->orderByDesc('started_at')->limit(50)->get(),
        ]);
    }
}
