<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeActivity;
use App\Models\EmployeeActivityCustomType;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tachograph-style employee activity API.
 *
 * Covers:
 *   - CRUD on individual activities
 *   - Per-worker day plan (gap-filled 24h timeline)
 *   - Per-worker month plan (day-keyed strips)
 *   - Team day (stacked tacho rows for all active workers)
 *   - Activity-type catalog (built-in types + tenant custom types)
 */
class EmployeeActivityController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = EmployeeActivity::with(['worker:id,code,name', 'workOrder:id,order_no', 'line:id,name'])
            ->orderBy('starts_at');

        if ($request->filled('worker_id')) {
            $query->where('worker_id', (int) $request->input('worker_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('date')) {
            $d = Carbon::parse($request->input('date'));
            $query->whereBetween('starts_at', [$d->copy()->startOfDay(), $d->copy()->endOfDay()]);
        } else {
            if ($request->filled('from')) {
                $query->where('starts_at', '>=', Carbon::parse($request->input('from')));
            }
            if ($request->filled('to')) {
                $query->where('starts_at', '<', Carbon::parse($request->input('to')));
            }
        }

        $perPage = max(1, min((int) $request->query('per_page', 100), 500));
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($a) => $this->serialize($a)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function show(EmployeeActivity $employeeActivity): JsonResponse
    {
        $employeeActivity->load(['worker:id,code,name', 'workOrder:id,order_no', 'line:id,name']);

        return response()->json(['data' => $this->serialize($employeeActivity)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $activity = EmployeeActivity::create(array_merge($data, [
            'created_by_id' => $request->user()?->id,
        ]));

        $activity->load(['worker:id,code,name', 'workOrder:id,order_no', 'line:id,name']);

        return response()->json([
            'message' => __('Activity created'),
            'data'    => $this->serialize($activity),
        ], 201);
    }

    public function update(Request $request, EmployeeActivity $employeeActivity): JsonResponse
    {
        $data = $this->validatePayload($request, $employeeActivity);
        $employeeActivity->update($data);
        $employeeActivity->load(['worker:id,code,name', 'workOrder:id,order_no', 'line:id,name']);

        return response()->json([
            'message' => __('Activity updated'),
            'data'    => $this->serialize($employeeActivity->fresh()),
        ]);
    }

    public function destroy(EmployeeActivity $employeeActivity): JsonResponse
    {
        $employeeActivity->delete();

        return response()->json(['message' => __('Activity deleted')]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Aggregations the mobile app actually renders
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Single worker's day plan — gap-filled tachograph for the requested date.
     * GET /api/v1/workers/{worker}/day-plan?date=YYYY-MM-DD
     */
    public function dayPlan(Request $request, Worker $worker): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $segments = $this->buildDaySegments($worker->id, $date);
        $sums = $this->summarize($segments);

        return response()->json([
            'data' => [
                'worker'     => [
                    'id'              => $worker->id,
                    'code'            => $worker->code,
                    'name'            => $worker->name,
                    'personnel_class' => $worker->personnelClass?->only(['id','code','name']),
                ],
                'date'       => $date->toDateString(),
                'segments'   => $segments,
                'summary'    => $sums,
                'type_meta'  => EmployeeActivity::TYPE_META,
            ],
        ]);
    }

    /**
     * Single worker's month plan — keyed by date, with mini-strip segments
     * suitable for rendering the calendar tachograph cells.
     * GET /api/v1/workers/{worker}/month-plan?month=YYYY-MM
     */
    public function monthPlan(Request $request, Worker $worker): JsonResponse
    {
        $anchor = $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->input('month').'-01')
            : Carbon::today()->startOfMonth();

        $monthStart = $anchor->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $anchor->copy()->endOfMonth()->endOfWeek();

        $rows = EmployeeActivity::where('worker_id', $worker->id)
            ->whereBetween('starts_at', [$monthStart, $monthEnd])
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn ($a) => $a->starts_at->toDateString());

        $days = [];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $key = $cursor->toDateString();
            $dayRows = $rows->get($key, collect());
            $segments = $this->segmentsFromRows($dayRows, $cursor);
            $sums = $this->summarize($segments);
            $days[] = [
                'date'       => $key,
                'in_month'   => $cursor->month === $anchor->month,
                'is_today'   => $cursor->isToday(),
                'segments'   => $segments,
                'on_duty'    => ($sums['work'] ?? 0) + ($sums['setup'] ?? 0) + ($sums['qc'] ?? 0)
                                + ($sums['maint'] ?? 0) + ($sums['meeting'] ?? 0) + ($sums['training'] ?? 0),
                'productive' => ($sums['work'] ?? 0) + ($sums['setup'] ?? 0) + ($sums['qc'] ?? 0),
            ];
            $cursor->addDay();
        }

        return response()->json([
            'data' => [
                'worker'      => ['id' => $worker->id, 'code' => $worker->code, 'name' => $worker->name],
                'month'       => $anchor->format('Y-m'),
                'month_start' => $monthStart->toDateString(),
                'month_end'   => $monthEnd->toDateString(),
                'days'        => $days,
                'type_meta'   => EmployeeActivity::TYPE_META,
            ],
        ]);
    }

    /**
     * All active workers — stacked tacho rows for a single date.
     * GET /api/v1/employee-activities/team-day?date=YYYY-MM-DD
     */
    public function teamDay(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $workers = Worker::with('personnelClass:id,code,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $onDutyTypes = ['work', 'setup', 'qc', 'maint', 'training', 'meeting'];

        $rows = $workers->map(function ($w) use ($date, $onDutyTypes) {
            $segments = $this->buildDaySegments($w->id, $date);
            $sums = $this->summarize($segments);
            $onDuty = 0;
            foreach ($onDutyTypes as $t) {
                $onDuty += $sums[$t] ?? 0;
            }
            return [
                'worker'   => [
                    'id'              => $w->id,
                    'code'            => $w->code,
                    'name'            => $w->name,
                    'personnel_class' => $w->personnelClass?->only(['id','code','name']),
                ],
                'segments' => $segments,
                'summary'  => $sums,
                'on_duty'  => $onDuty,
            ];
        });

        return response()->json([
            'data' => [
                'date'      => $date->toDateString(),
                'rows'      => $rows,
                'type_meta' => EmployeeActivity::TYPE_META,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Catalog
    // ─────────────────────────────────────────────────────────────────────

    public function types(): JsonResponse
    {
        $built = collect(EmployeeActivity::TYPE_META)
            ->map(fn ($meta, $key) => [
                'key'      => $key,
                'label'    => $meta['label'],
                'short'    => $meta['short'],
                'color'    => $meta['color'],
                'custom'   => false,
            ])
            ->values();

        $custom = EmployeeActivityCustomType::where('is_active', true)
            ->orderBy('label')
            ->get()
            ->map(fn ($c) => [
                'key'    => 'custom',
                'code'   => $c->code,
                'label'  => $c->label,
                'short'  => mb_strtoupper(mb_substr($c->code, 0, 3)),
                'color'  => $c->color,
                'icon'   => $c->icon,
                'custom' => true,
            ]);

        return response()->json([
            'data' => [
                'built_in' => $built,
                'custom'   => $custom,
            ],
        ]);
    }

    public function storeCustomType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'  => ['required', 'string', 'max:64', Rule::unique('employee_activity_custom_types', 'code')],
            'label' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:16'],
            'icon'  => ['nullable', 'string', 'max:32'],
        ]);

        $type = EmployeeActivityCustomType::create($data + ['is_active' => true]);

        return response()->json(['message' => __('Custom type created'), 'data' => $type], 201);
    }

    public function updateCustomType(Request $request, EmployeeActivityCustomType $employeeActivityCustomType): JsonResponse
    {
        $data = $request->validate([
            'code'      => ['sometimes', 'string', 'max:64', Rule::unique('employee_activity_custom_types', 'code')->ignore($employeeActivityCustomType->id)],
            'label'     => ['sometimes', 'string', 'max:255'],
            'color'     => ['sometimes', 'nullable', 'string', 'max:16'],
            'icon'      => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $employeeActivityCustomType->update($data);

        return response()->json(['message' => __('Custom type updated'), 'data' => $employeeActivityCustomType->fresh()]);
    }

    public function destroyCustomType(EmployeeActivityCustomType $employeeActivityCustomType): JsonResponse
    {
        $employeeActivityCustomType->delete();

        return response()->json(['message' => __('Custom type deleted')]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function serialize(EmployeeActivity $a): array
    {
        return [
            'id'             => $a->id,
            'worker_id'      => $a->worker_id,
            'worker'         => $a->relationLoaded('worker') ? $a->worker?->only(['id','code','name']) : null,
            'type'           => $a->type,
            'custom_code'    => $a->custom_code,
            'label'          => $a->label,
            'starts_at'      => $a->starts_at?->toIso8601String(),
            'ends_at'        => $a->ends_at?->toIso8601String(),
            'from'           => $a->starts_at?->format('H:i'),
            'to'             => $a->ends_at?->format('H:i'),
            'duration_min'   => $a->durationMinutes(),
            'work_order_id'  => $a->work_order_id,
            'work_order'     => $a->relationLoaded('workOrder') ? $a->workOrder?->only(['id','order_no']) : null,
            'line_id'        => $a->line_id,
            'line'           => $a->relationLoaded('line') ? $a->line?->only(['id','name']) : null,
            'step_name'      => $a->step_name,
            'notes'          => $a->notes,
            'created_at'     => $a->created_at?->toIso8601String(),
            'updated_at'     => $a->updated_at?->toIso8601String(),
        ];
    }

    private function validatePayload(Request $request, ?EmployeeActivity $existing = null): array
    {
        $rules = [
            'worker_id'     => ['required', 'exists:workers,id'],
            'type'          => ['required', Rule::in(EmployeeActivity::TYPES)],
            'custom_code'   => ['nullable', 'string', 'max:64'],
            'label'         => ['nullable', 'string', 'max:255'],
            'starts_at'     => ['required', 'date'],
            'ends_at'       => ['required', 'date', 'after:starts_at'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'line_id'       => ['nullable', 'exists:lines,id'],
            'step_name'     => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string'],
        ];

        if ($existing) {
            foreach ($rules as $k => $v) {
                $rules[$k] = array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $v);
            }
        }

        return $request->validate($rules);
    }

    /**
     * Build a gap-filled (24h) segment list for a worker on a given day.
     */
    private function buildDaySegments(int $workerId, Carbon $date): array
    {
        $rows = EmployeeActivity::with(['workOrder:id,order_no', 'line:id,name'])
            ->where('worker_id', $workerId)
            ->whereBetween('starts_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        return $this->segmentsFromRows($rows, $date);
    }

    /**
     * Turn an ordered collection of activity rows for a single day into a
     * gap-filled segment list (off blocks fill the holes so the tachograph
     * always covers 00:00 → 24:00).
     */
    private function segmentsFromRows($rows, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay()->addSecond();
        $segments = [];
        $cursor = $start->copy();

        foreach ($rows as $row) {
            if ($row->starts_at->gt($cursor)) {
                $segments[] = $this->offSegment($cursor, $row->starts_at);
            }
            $segments[] = [
                'id'            => $row->id,
                'type'          => $row->type,
                'custom_code'   => $row->custom_code,
                'label'         => $row->label,
                'from'          => $row->starts_at->format('H:i'),
                'to'            => $row->ends_at->format('H:i'),
                'duration_min'  => $row->durationMinutes(),
                'work_order'    => $row->workOrder?->only(['id','order_no']),
                'line'          => $row->line?->only(['id','name']),
                'step_name'     => $row->step_name,
                'notes'         => $row->notes,
            ];
            $cursor = $row->ends_at->copy();
        }

        if ($cursor->lt($end->copy()->subSecond())) {
            $segments[] = $this->offSegment($cursor, $end->copy()->subSecond());
        }

        return $segments;
    }

    private function offSegment(Carbon $from, Carbon $to): array
    {
        return [
            'id'           => null,
            'type'         => 'off',
            'custom_code'  => null,
            'label'        => null,
            'from'         => $from->format('H:i'),
            'to'           => $to->format('H:i') === '23:59' ? '24:00' : $to->format('H:i'),
            'duration_min' => max(0, (int) round($from->diffInMinutes($to))),
            'work_order'   => null,
            'line'         => null,
            'step_name'    => null,
            'notes'        => null,
        ];
    }

    /** Total minutes per type. */
    private function summarize(array $segments): array
    {
        $sums = [];
        foreach ($segments as $s) {
            $sums[$s['type']] = ($sums[$s['type']] ?? 0) + ($s['duration_min'] ?? 0);
        }
        return $sums;
    }
}
