<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleCapacityCellRequest;
use App\Services\Schedule\CapacityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-side schedule-capacity API for the mobile app — available vs planned
 * hours per resource per time bucket, on the line (machine) or crew (labor)
 * axis. Mirrors the web Admin\ScheduleCapacityController grid; rescheduling
 * reuses the planner's existing PUT /api/v1/schedule/{workOrder} endpoint.
 * Authorization is the role:Admin|Supervisor middleware on the route.
 */
class ScheduleCapacityController extends Controller
{
    public function index(Request $request, CapacityService $capacity): JsonResponse
    {
        $axis = in_array($request->query('axis'), ['line', 'crew'], true)
            ? $request->query('axis')
            : 'line';

        $granularity = in_array($request->query('granularity'), ['day', 'week'], true)
            ? $request->query('granularity')
            : 'week';

        [$rangeStart, $rangeEnd, $navPrev, $navNext] = $this->resolveRange($request, $granularity);

        $grid = $axis === 'crew'
            ? $capacity->crewCapacity($rangeStart, $rangeEnd, $granularity)
            : $capacity->lineCapacity($rangeStart, $rangeEnd, $granularity);

        return response()->json([
            'grid' => $grid,
            'granularity' => $granularity,
            'axis' => $axis,
            'range_start' => $rangeStart->toDateString(),
            'range_end' => $rangeEnd->toDateString(),
            'nav_prev' => $navPrev->toDateString(),
            'nav_next' => $navNext->toDateString(),
        ]);
    }

    /** Work orders contributing to a single line-axis cell (the drill-down). */
    public function cellOrders(ScheduleCapacityCellRequest $request, CapacityService $capacity): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'orders' => $capacity->cellOrders(
                (int) $validated['line_id'],
                Carbon::parse($validated['start']),
                Carbon::parse($validated['end']),
            ),
        ]);
    }

    /**
     * Resolve [rangeStart, rangeEnd, navPrev, navNext] for the granularity.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    private function resolveRange(Request $request, string $granularity): array
    {
        $rawStart = $request->filled('start_date')
            ? Carbon::parse($request->query('start_date'))
            : now();

        if ($granularity === 'day') {
            $rangeStart = $rawStart->copy()->startOfDay();

            return [
                $rangeStart,
                $rangeStart->copy()->addDays(13)->endOfDay(), // ~2 weeks of days
                $rangeStart->copy()->subDays(14),
                $rangeStart->copy()->addDays(14),
            ];
        }

        $rangeStart = $rawStart->copy()->startOfWeek();

        return [
            $rangeStart,
            $rangeStart->copy()->addWeeks(8)->subDay()->endOfDay(), // ~quarter
            $rangeStart->copy()->subWeeks(8),
            $rangeStart->copy()->addWeeks(8),
        ];
    }
}
