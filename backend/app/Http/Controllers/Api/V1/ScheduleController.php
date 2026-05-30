<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mirror of Web/Admin/SchedulePlannerController write actions —
 * `PUT /api/v1/schedule/{workOrder}` and `/resize`. Mobile uses these to
 * reschedule WOs (move to another line, change start/end). Conflict detection
 * matches the web controller exactly: 409 with {conflict: true} when another
 * active WO on the same line overlaps, unless `force_conflict=1`.
 */
class ScheduleController extends Controller
{
    public function updateOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $request->validate([
            'line_id'          => 'nullable|exists:lines,id',
            'due_date'         => 'nullable|date',
            'end_date'         => 'nullable|date|after_or_equal:due_date',
            'week_number'      => 'nullable|integer|min:1|max:53',
            'shift_number'     => 'nullable|integer|min:1|max:10',
            'end_shift_number' => 'nullable|integer|min:1|max:10',
            'planned_start_at' => 'nullable|date',
            'planned_end_at'   => 'nullable|date|after:planned_start_at',
        ]);

        $data = [];
        // Always-overwritable fields (web controller convention: empty = clear).
        foreach (['line_id', 'due_date', 'week_number', 'shift_number'] as $f) {
            if ($request->has($f)) {
                $data[$f] = $request->input($f) ?: null;
            }
        }
        // Optional span fields — only touch when present.
        foreach (['end_date', 'end_shift_number', 'planned_start_at', 'planned_end_at'] as $f) {
            if ($request->has($f)) {
                $data[$f] = $request->input($f) ?: null;
            }
        }

        // Conflict detection — refuse overlap on the same line unless forced.
        $targetLineId = array_key_exists('line_id', $data) ? $data['line_id'] : $workOrder->line_id;
        if (! empty($data['planned_start_at']) && ! empty($data['planned_end_at']) && $targetLineId) {
            $conflict = WorkOrder::query()
                ->where('line_id', $targetLineId)
                ->where('id', '!=', $workOrder->id)
                ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
                ->whereNotNull('planned_start_at')
                ->whereNotNull('planned_end_at')
                ->where('planned_start_at', '<', $data['planned_end_at'])
                ->where('planned_end_at', '>', $data['planned_start_at'])
                ->exists();

            if ($conflict && ! $request->boolean('force_conflict')) {
                return response()->json([
                    'success'  => false,
                    'conflict' => true,
                    'message'  => 'This time slot overlaps another work order on the same line.',
                ], 409);
            }
        }

        $workOrder->update($data);

        return response()->json([
            'success' => true,
            'data'    => $workOrder->fresh(),
        ]);
    }

    public function resizeOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'planned_start_at' => 'required|date',
            'planned_end_at'   => 'required|date|after:planned_start_at',
        ]);

        if ($workOrder->line_id) {
            $conflict = WorkOrder::query()
                ->where('line_id', $workOrder->line_id)
                ->where('id', '!=', $workOrder->id)
                ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
                ->whereNotNull('planned_start_at')
                ->whereNotNull('planned_end_at')
                ->where('planned_start_at', '<', $validated['planned_end_at'])
                ->where('planned_end_at', '>', $validated['planned_start_at'])
                ->exists();

            if ($conflict && ! $request->boolean('force_conflict')) {
                return response()->json([
                    'success'  => false,
                    'conflict' => true,
                    'message'  => 'This time slot overlaps another work order on the same line.',
                ], 409);
            }
        }

        $workOrder->update([
            'planned_start_at' => $validated['planned_start_at'],
            'planned_end_at'   => $validated['planned_end_at'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $workOrder->fresh(),
        ]);
    }
}
