<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeActivity;
use App\Models\EmployeeActivityCustomType;
use App\Models\Worker;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Employee day / week / month / team-day planner — tachograph-style activity
 * timelines per worker. Exposes a single index() that dispatches on the
 * `view` query parameter (day | team | month).
 */
class EmployeeScheduleController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view', 'day');
        if (! in_array($view, ['day', 'team', 'month'], true)) {
            $view = 'day';
        }

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $workers = Worker::with('personnelClass')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedWorkerId = (int) ($request->input('worker_id') ?? $workers->first()?->id);
        $selectedWorker = $workers->firstWhere('id', $selectedWorkerId) ?? $workers->first();

        $customTypes = EmployeeActivityCustomType::where('is_active', true)
            ->orderBy('label')
            ->get();

        $workersFlat = $workers->map(fn ($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'code' => $w->code,
            'personnel_class_code' => $w->personnelClass?->code ?? $w->personnelClass?->name,
        ])->values()->all();

        $customTypesFlat = $customTypes->map(fn ($c) => [
            'code' => $c->code,
            'label' => $c->label,
            'color' => $c->color,
        ])->values()->all();

        $baseProps = [
            'view' => $view,
            'date' => $date->format('Y-m-d'),
            'workers' => $workersFlat,
            'selectedWorker' => $selectedWorker ? [
                'id' => $selectedWorker->id,
                'name' => $selectedWorker->name,
                'code' => $selectedWorker->code,
            ] : null,
            'selectedWorkerId' => $selectedWorker?->id,
            'customTypes' => $customTypesFlat,
            'typeMeta' => EmployeeActivity::TYPE_META,
        ];

        if ($view === 'day') {
            $baseProps['activities'] = $selectedWorker
                ? $this->dayActivities($selectedWorker->id, $date)
                : [];

            return Inertia::render('admin/schedule/employees/Day', $baseProps);
        }

        if ($view === 'team') {
            $baseProps['teamActivities'] = $workers->mapWithKeys(
                fn ($w) => [$w->id => $this->dayActivities($w->id, $date)]
            )->all();

            return Inertia::render('admin/schedule/employees/Team', $baseProps);
        }

        // month
        $monthStart = $date->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $date->copy()->endOfMonth()->endOfWeek();

        $monthByWorkerRaw = [];
        if ($selectedWorker) {
            $monthByWorkerRaw = EmployeeActivity::where('worker_id', $selectedWorker->id)
                ->whereBetween('starts_at', [$monthStart, $monthEnd])
                ->orderBy('starts_at')
                ->get()
                ->groupBy(fn ($a) => $a->starts_at->toDateString())
                ->map(fn ($acts) => $acts->map(fn ($a) => [
                    'type' => $a->type,
                    'starts_at_time' => $a->starts_at->format('H:i'),
                    'ends_at_time' => $a->ends_at->format('H:i'),
                ])->values()->all())
                ->all();
        }

        $baseProps['monthStart'] = $monthStart->format('Y-m-d');
        $baseProps['monthEnd'] = $monthEnd->format('Y-m-d');
        $baseProps['monthByWorker'] = $monthByWorkerRaw;
        $baseProps['selectedDayActivities'] = $this->dayActivities($selectedWorker?->id ?? 0, $date);

        return Inertia::render('admin/schedule/employees/Month', $baseProps);
    }

    /**
     * Show the add-activity form for a worker on a specific date.
     */
    public function create(Request $request)
    {
        $workerId = (int) $request->input('worker_id');
        $worker = Worker::findOrFail($workerId);
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $defaultFrom = $date->copy()->setTime((int) now()->format('H'), 0);
        $defaultTo = $defaultFrom->copy()->addHour();

        $workOrders = WorkOrder::with('productType')->orderBy('order_no')->limit(50)->get()
            ->map(fn ($wo) => [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'product_name' => $wo->productType?->name,
            ])->values()->all();

        $customTypes = EmployeeActivityCustomType::where('is_active', true)->orderBy('label')->get()
            ->map(fn ($c) => ['code' => $c->code, 'label' => $c->label, 'color' => $c->color])
            ->values()->all();

        return Inertia::render('admin/schedule/employees/Create', [
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
                'code' => $worker->code,
            ],
            'date' => $date->format('Y-m-d'),
            'workOrders' => $workOrders,
            'customTypes' => $customTypes,
            'typeMeta' => EmployeeActivity::TYPE_META,
            'defaultFrom' => $defaultFrom->format('H:i'),
            'defaultTo' => $defaultTo->format('H:i'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'type' => 'required|in:'.implode(',', EmployeeActivity::TYPES),
            'custom_code' => 'nullable|string|max:64',
            'label' => 'nullable|string|max:255',
            'date' => 'required|date',
            'from_time' => 'required|date_format:H:i',
            'to_time' => 'required|date_format:H:i|after:from_time',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'notes' => 'nullable|string',
        ]);

        $date = Carbon::parse($validated['date']);
        $startsAt = $date->copy()->setTimeFromTimeString($validated['from_time']);
        $endsAt = $date->copy()->setTimeFromTimeString($validated['to_time']);

        EmployeeActivity::create([
            'worker_id' => $validated['worker_id'],
            'type' => $validated['type'],
            'custom_code' => $validated['custom_code'] ?? null,
            'label' => $validated['label'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'work_order_id' => $validated['work_order_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by_id' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.schedule.employees', [
                'view' => 'day',
                'date' => $date->toDateString(),
                'worker_id' => $validated['worker_id'],
            ])
            ->with('status', __('Activity added.'));
    }

    public function destroy(EmployeeActivity $activity)
    {
        $date = $activity->starts_at->toDateString();
        $workerId = $activity->worker_id;
        $activity->delete();

        return redirect()->route('admin.schedule.employees', [
            'view' => 'day',
            'date' => $date,
            'worker_id' => $workerId,
        ])->with('status', __('Activity removed.'));
    }

    /**
     * Return ordered activities for a worker on a given day, filling gaps with
     * implicit "off" blocks so the tachograph always covers 00:00 → 24:00.
     */
    private function dayActivities(int $workerId, Carbon $date): array
    {
        if (! $workerId) {
            return [];
        }

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $rows = EmployeeActivity::with(['workOrder', 'line'])
            ->where('worker_id', $workerId)
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get();

        $segments = [];
        $cursor = $start->copy();
        foreach ($rows as $row) {
            if ($row->starts_at->gt($cursor)) {
                $segments[] = [
                    'id' => null,
                    'type' => 'off',
                    'from' => $cursor->format('H:i'),
                    'to' => $row->starts_at->format('H:i'),
                    'duration' => (int) $cursor->diffInMinutes($row->starts_at),
                    'label' => null,
                    'wo' => null,
                    'step' => null,
                    'notes' => null,
                ];
            }
            $segments[] = [
                'id' => $row->id,
                'type' => $row->type,
                'from' => $row->starts_at->format('H:i'),
                'to' => $row->ends_at->format('H:i'),
                'duration' => $row->durationMinutes(),
                'label' => $row->label,
                'wo' => $row->workOrder?->order_no,
                'step' => $row->step_name,
                'notes' => $row->notes,
            ];
            $cursor = $row->ends_at->copy();
        }
        if ($cursor->lt($end)) {
            $segments[] = [
                'id' => null,
                'type' => 'off',
                'from' => $cursor->format('H:i'),
                'to' => '24:00',
                'duration' => (int) $cursor->diffInMinutes($date->copy()->addDay()->startOfDay()),
                'label' => null,
                'wo' => null,
                'step' => null,
                'notes' => null,
            ];
        }

        return $segments;
    }
}
