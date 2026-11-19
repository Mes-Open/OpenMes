<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Schedule\StoreEmployeeActivityRequest;
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

        $hasPersonnelClass = Worker::hasModuleRelation('personnelClass');
        $workers = Worker::query()
            ->when($hasPersonnelClass, fn ($query) => $query->with('personnelClass'))
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
            'personnel_class_code' => $hasPersonnelClass
                ? ($w->personnelClass?->code ?? $w->personnelClass?->name)
                : null,
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
            $rows = EmployeeActivity::where('worker_id', $selectedWorker->id)
                ->where('starts_at', '<', $monthEnd)
                ->where('ends_at', '>', $monthStart)
                ->orderBy('starts_at')
                ->get();
            for ($day = $monthStart->copy()->startOfDay(); $day->lte($monthEnd); $day->addDay()) {
                $pieces = $this->piecesOn($rows, $day);
                if ($pieces !== []) {
                    $monthByWorkerRaw[$day->toDateString()] = array_map(fn ($p) => [
                        'type' => $p['row']->type,
                        'starts_at_time' => $p['from'],
                        'ends_at_time' => $p['to'],
                    ], $pieces);
                }
            }
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

    public function store(StoreEmployeeActivityRequest $request)
    {
        $validated = $request->validated();
        [$startsAt, $endsAt] = $request->window();

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
                'date' => $startsAt->toDateString(),
                'worker_id' => $validated['worker_id'],
            ])
            ->with('success', $endsAt->isSameDay($startsAt)
                ? __('Activity added.')
                : __('Activity added. It ends the next day at :time.', ['time' => $endsAt->format('H:i')]));
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
        ])->with('success', __('Activity removed.'));
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
            ->where('starts_at', '<', $date->copy()->addDay()->startOfDay())
            ->where('ends_at', '>', $start)
            ->orderBy('starts_at')
            ->get();

        $segments = [];
        $cursor = $start->copy();
        foreach ($this->piecesOn($rows, $date) as $piece) {
            $row = $piece['row'];
            if ($piece['start']->gt($cursor)) {
                $segments[] = [
                    'id' => null,
                    'type' => 'off',
                    'from' => $cursor->format('H:i'),
                    'to' => $piece['from'],
                    'duration' => (int) $cursor->diffInMinutes($piece['start']),
                    'label' => null,
                    'wo' => null,
                    'step' => null,
                    'notes' => null,
                ];
            }
            $segments[] = [
                'id' => $row->id,
                'type' => $row->type,
                'from' => $piece['from'],
                'to' => $piece['to'],
                'duration' => (int) $piece['start']->diffInMinutes($piece['end']),
                'continues' => $piece['continues'],
                'continued' => $piece['continued'],
                'label' => $row->label,
                'wo' => $row->workOrder?->order_no,
                'step' => $row->step_name,
                'notes' => $row->notes,
            ];
            $cursor = $piece['end']->max($cursor)->copy();
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

    /**
     * The part of each activity that falls on one day, in order. A night
     * block 22:00–06:00 shows as 22:00–24:00 on its first day and 00:00–06:00
     * on the next, flagged so the strip can say it carries on.
     *
     * @param  iterable<EmployeeActivity>  $rows
     * @return list<array{row: EmployeeActivity, start: Carbon, end: Carbon, from: string, to: string, continues: bool, continued: bool}>
     */
    private function piecesOn(iterable $rows, Carbon $date): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $pieces = [];
        foreach ($rows as $row) {
            if (! $row->starts_at->lt($dayEnd) || ! $row->ends_at->gt($dayStart)) {
                continue;
            }
            $start = $row->starts_at->max($dayStart)->copy();
            $end = $row->ends_at->min($dayEnd)->copy();
            $pieces[] = [
                'row' => $row,
                'start' => $start,
                'end' => $end,
                'from' => $start->format('H:i'),
                'to' => $end->eq($dayEnd) ? '24:00' : $end->format('H:i'),
                'continues' => $row->ends_at->gt($dayEnd),
                'continued' => $row->starts_at->lt($dayStart),
            ];
        }
        usort($pieces, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $pieces;
    }
}
