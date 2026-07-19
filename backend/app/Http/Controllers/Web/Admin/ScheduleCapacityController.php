<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleCapacityCellRequest;
use App\Models\Line;
use App\Services\Schedule\CapacityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Schedule capacity view — available hours vs planned/used hours per resource
 * per time bucket, on the line (machine) or crew (labor) axis. Authorization
 * is handled by the `tab.access` middleware on the /admin/schedule prefix.
 *
 * Rescheduling from this view reuses the planner's existing
 * `PUT /admin/schedule/{workOrder}` endpoint; this controller only adds the
 * read-side cell drill-down ({@see cellOrders()}).
 */
class ScheduleCapacityController extends Controller
{
    public function index(Request $request, CapacityService $capacity)
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

        return Inertia::render('admin/schedule/Capacity', [
            'grid' => $grid,
            'granularity' => $granularity,
            'axis' => $axis,
            'startDate' => $rangeStart->toDateString(),
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'navPrev' => $navPrev->toDateString(),
            'navNext' => $navNext->toDateString(),
            'lines' => Line::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'code'])->all(),
        ]);
    }

    /**
     * Work orders contributing to a single line-axis cell (the drill-down).
     */
    public function cellOrders(ScheduleCapacityCellRequest $request, CapacityService $capacity)
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
