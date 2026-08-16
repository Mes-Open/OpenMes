<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ScheduleResizeOrderRequest;
use App\Http\Requests\Api\V1\ScheduleUpdateOrderRequest;
use App\Models\ScheduleChangeLog;
use App\Models\WorkOrder;
use App\Services\Schedule\SchedulePlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API surface of the schedule planner for the mobile app. Every action routes
 * through SchedulePlannerService — the same brain the Inertia web planner uses
 * — so mobile edits sync extra placements and land in the audit log (and so
 * stay undoable) exactly like browser edits.
 *
 * Conflict semantics match the web planner: 409 with {conflict: true} when
 * another active WO on the same line overlaps, unless `force_conflict=1`.
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly SchedulePlannerService $planner) {}

    /** The planner board: lines, shifts, orders in range, backlog, maintenance. */
    public function board(Request $request): JsonResponse
    {
        $request->validate([
            'view_mode' => ['nullable', 'string', 'in:'.implode(',', SchedulePlannerService::VIEW_MODES)],
            'start_date' => ['nullable', 'date'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
        ]);

        return response()->json([
            'data' => $this->planner->board($request->only(['view_mode', 'start_date', 'line_id'])),
        ]);
    }

    public function updateOrder(ScheduleUpdateOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $result = $this->planner->updateOrder(
            $workOrder,
            $request->placementInput(),
            $request->boolean('force_conflict'),
        );

        if ($result['conflict']) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => $result['message'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'warnings' => $result['warnings'],
            'data' => $this->planner->flattenOrder($workOrder->fresh(['productType', 'line', 'customer', 'extraPlacements'])),
        ]);
    }

    public function resizeOrder(ScheduleResizeOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $result = $this->planner->resizeOrder(
            $workOrder,
            $request->only(['planned_start_at', 'planned_end_at']),
            $request->boolean('force_conflict'),
        );

        if ($result['conflict']) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => $result['message'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => $this->planner->flattenOrder($workOrder->fresh(['productType', 'line', 'customer', 'extraPlacements'])),
        ]);
    }

    /** The last planner edits, newest first — the Changes tab. */
    public function changes(): JsonResponse
    {
        return response()->json(['data' => $this->planner->recentChanges()]);
    }

    public function undoChange(ScheduleChangeLog $change): JsonResponse
    {
        if (! $this->planner->undoChange($change)) {
            return response()->json([
                'success' => false,
                'message' => __('Work order no longer exists.'),
            ], 410);
        }

        return response()->json(['success' => true, 'message' => __('Change undone.')]);
    }
}
