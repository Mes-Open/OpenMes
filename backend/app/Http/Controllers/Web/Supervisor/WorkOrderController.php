<?php

namespace App\Http\Controllers\Web\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreWorkOrderRequest;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Models\WorkstationState;
use App\Services\CustomFieldService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkOrderController extends Controller
{
    private const ACTIVE_MACHINE_STEP_STATUSES = [
        BatchStep::STATUS_READY,
        BatchStep::STATUS_IN_PROGRESS,
    ];

    public function index(Request $request)
    {
        $counts = WorkOrder::withCount('batches')->get(['id'])
            ->mapWithKeys(fn ($w) => [$w->id => $w->batches_count]);

        return Inertia::render('supervisor/work-orders/Index', [
            'counts' => $counts,
            'lineNames' => Line::pluck('name', 'id'),
            'productTypeNames' => ProductType::pluck('name', 'id'),
        ]);
    }

    /**
     * Create a work order. Supervisors hold the `create work orders` ability
     * (WorkOrderPolicy); this mirrors the admin flow but stays in /supervisor.
     */
    public function create(CustomFieldService $customFields)
    {
        $this->authorize('create', WorkOrder::class);

        return Inertia::render('supervisor/work-orders/Create', [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customFields' => $customFields->clientConfig('work_order'),
        ]);
    }

    public function store(StoreWorkOrderRequest $request, WorkOrderService $workOrderService, CustomFieldService $cf)
    {
        $this->authorize('create', WorkOrder::class);

        $validated = $request->validated();
        unset($validated['custom_field_files']);

        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'work_order') ?: null;
        }

        try {
            $workOrder = $workOrderService->createWorkOrder($validated);
        } catch (\Exception $e) {
            report($e);

            return back()->withInput()
                ->with('error', __('Failed to create work order. Please check your input and try again.'));
        }

        return redirect()->route('supervisor.work-orders.index')
            ->with('success', "Work order {$workOrder->order_no} created.");
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(['line', 'productType', 'batches.workstation.line', 'batches.steps.workstation.line', 'issues.issueType', 'issues.reportedBy']);

        $batches = $workOrder->batches->map(function ($batch) {
            return [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'status' => $batch->status,
                'produced_qty' => $batch->produced_qty,
                'target_qty' => $batch->target_qty,
                'started_at' => $batch->started_at?->toISOString(),
                'completed_at' => $batch->completed_at?->toISOString(),
                'steps' => $batch->steps->map(fn ($s) => [
                    'id' => $s->id,
                    'step_number' => $s->step_number,
                    'name' => $s->name,
                    'status' => $s->status,
                    'workstation_id' => $s->workstation_id,
                    'duration_minutes' => $s->duration_minutes,
                ])->values(),
            ];
        })->values();

        $issues = $workOrder->issues->map(fn ($i) => [
            'id' => $i->id,
            'title' => $i->title,
            'status' => $i->status,
            'issue_type_name' => $i->issueType?->name,
            'is_blocking' => (bool) ($i->issueType?->is_blocking ?? false),
        ])->values();

        return Inertia::render('supervisor/work-orders/Show', [
            'workOrder' => [
                'id' => $workOrder->id,
                'order_no' => $workOrder->order_no,
                'customer_order_no' => $workOrder->customer_order_no,
                'status' => $workOrder->status,
                'planned_qty' => $workOrder->planned_qty,
                'produced_qty' => $workOrder->produced_qty,
                'priority' => $workOrder->priority,
                'due_date' => $workOrder->due_date?->toDateString(),
                'description' => $workOrder->description,
                'process_snapshot' => $workOrder->process_snapshot,
                'created_at' => $workOrder->created_at->toISOString(),
                'line_name' => $workOrder->line?->name,
                'product_type_name' => $workOrder->productType?->name,
                'batches' => $batches,
                'issues' => $issues,
                'machines' => $this->machinesForWorkOrder($workOrder),
            ],
        ]);
    }

    private function machinesForWorkOrder(WorkOrder $workOrder)
    {
        $machineRows = $workOrder->batches->flatMap(function ($batch) {
            $rows = collect();

            if ($batch->workstation_id && $batch->workstation) {
                $rows->push([
                    'workstation' => $batch->workstation,
                    'step_status' => null,
                ]);
            }

            return $rows->merge(
                $batch->steps
                    ->filter(fn ($step) => $step->workstation_id && $step->workstation)
                    ->map(fn ($step) => [
                        'workstation' => $step->workstation,
                        'step_status' => $step->status,
                    ])
            );
        });

        $workstationIds = $machineRows
            ->map(fn ($row) => $row['workstation']->id)
            ->unique()
            ->values();

        if ($workstationIds->isEmpty()) {
            return collect();
        }

        $currentStates = WorkstationState::query()
            ->whereIn('workstation_id', $workstationIds)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->get()
            ->unique('workstation_id')
            ->keyBy('workstation_id');

        return $machineRows
            ->groupBy(fn ($row) => $row['workstation']->id)
            ->map(function ($rows) use ($currentStates) {
                $workstation = $rows->first()['workstation'];
                $currentState = $currentStates->get($workstation->id);
                $stepStatuses = $rows->pluck('step_status')->filter();

                return [
                    'id' => $workstation->id,
                    'code' => $workstation->code,
                    'name' => $workstation->name,
                    'line_name' => $workstation->line?->name,
                    'is_active' => (bool) $workstation->is_active,
                    'current_state' => $currentState?->state,
                    'state_started_at' => $currentState?->started_at?->toISOString(),
                    'state_source' => $currentState?->source,
                    'steps_count' => $stepStatuses->count(),
                    'active_steps_count' => $stepStatuses
                        ->filter(fn ($status) => in_array($status, self::ACTIVE_MACHINE_STEP_STATUSES, true))
                        ->count(),
                    'step_statuses' => $stepStatuses
                        ->countBy()
                        ->map(fn ($count, $status) => ['status' => $status, 'count' => $count])
                        ->values(),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    public function accept(WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Only PENDING work orders can be accepted.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_ACCEPTED]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} accepted.");
    }

    public function reject(WorkOrder $workOrder)
    {
        if (! in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])) {
            return redirect()->back()->with('error', 'Only PENDING or ACCEPTED work orders can be rejected.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_REJECTED]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} rejected.");
    }

    public function pause(WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
            return redirect()->back()->with('error', 'Only IN_PROGRESS work orders can be paused.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_PAUSED]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} paused.");
    }

    public function resume(WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_PAUSED) {
            return redirect()->back()->with('error', 'Only PAUSED work orders can be resumed.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} resumed.");
    }

    public function complete(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
            return redirect()->back()->with('error', 'Only IN_PROGRESS work orders can be completed.');
        }

        $validated = $request->validate([
            'produced_qty' => 'required|numeric|min:0.01|max:99999999',
        ]);

        $workOrder->update([
            'status' => WorkOrder::STATUS_DONE,
            'produced_qty' => $validated['produced_qty'],
            'completed_at' => now(),
        ]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} completed.");
    }

    public function cancel(WorkOrder $workOrder)
    {
        if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()->with('error', 'Cannot cancel a work order in terminal state.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_CANCELLED]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} cancelled.");
    }

    public function reopen(WorkOrder $workOrder)
    {
        if (! in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()->with('error', 'Only terminal work orders can be reopened.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} reopened.");
    }

    public function edit(WorkOrder $workOrder)
    {
        return Inertia::render('supervisor/work-orders/Edit', [
            'workOrder' => [
                ...$workOrder->only('id', 'order_no', 'customer_order_no', 'line_id', 'product_type_id', 'planned_qty', 'priority', 'description', 'status'),
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
            ],
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $validated = $request->validate([
            'order_no' => 'required|string|max:100|unique:work_orders,order_no,'.$workOrder->id,
            'customer_order_no' => 'nullable|string|max:100',
            'line_id' => 'nullable|exists:lines,id',
            'product_type_id' => 'nullable|exists:product_types,id',
            'planned_qty' => 'required|numeric|min:0.01|max:99999999',
            'priority' => 'nullable|integer|min:0|max:100',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:PENDING,ACCEPTED,IN_PROGRESS,PAUSED,BLOCKED,DONE,REJECTED,CANCELLED',
        ]);

        $workOrder->update($validated);

        return redirect()->route('supervisor.work-orders.show', $workOrder)
            ->with('success', "Work order {$workOrder->order_no} updated.");
    }
}
