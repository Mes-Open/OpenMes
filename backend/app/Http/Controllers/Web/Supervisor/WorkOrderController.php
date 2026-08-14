<?php

namespace App\Http\Controllers\Web\Supervisor;

use App\Http\Controllers\Concerns\BuildsWorkOrderFormOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\BulkWorkOrderActionRequest;
use App\Http\Requests\Web\Admin\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\ResumeWorkOrderRequest;
use App\Models\BatchStep;
use App\Models\Customer;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Models\WorkstationState;
use App\Services\CustomFieldService;
use App\Services\WorkOrder\WorkOrderService;
use App\Services\WorkOrder\WorkOrderStopService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkOrderController extends Controller
{
    use BuildsWorkOrderFormOptions;

    private const ACTIVE_MACHINE_STEP_STATUSES = [
        BatchStep::STATUS_READY,
        BatchStep::STATUS_IN_PROGRESS,
    ];

    public function index(Request $request, CustomFieldService $customFields)
    {
        $counts = WorkOrder::withCount('batches')->get(['id'])
            ->mapWithKeys(fn ($w) => [$w->id => $w->batches_count]);

        return Inertia::render('supervisor/work-orders/Index', [
            'counts' => $counts,
            // Name maps cover every line/product/customer an existing row might
            // reference, including deactivated ones — unlike the create-form
            // options below, which only offer what is still selectable.
            'lineNames' => Line::pluck('name', 'id'),
            'productTypeNames' => ProductType::pluck('name', 'id'),
            'customerNames' => Customer::pluck('name', 'id'),
            // Deleting is admin-only in the shipped role set, so the list's
            // Delete item is gated on the ability rather than on the section —
            // a supervisor granted `delete work orders` gets it here too.
            'can' => ['delete' => (bool) $request->user()?->can('delete work orders')],
            // Feeds the list's "New work order" modal, which renders the same form
            // as the create page.
            ...$this->createFormOptions($customFields),
        ]);
    }

    /**
     * Create a work order. Supervisors hold the `create work orders` ability
     * (WorkOrderPolicy); this mirrors the admin flow but stays in /supervisor.
     */
    public function create(CustomFieldService $customFields)
    {
        $this->authorize('create', WorkOrder::class);

        return Inertia::render('supervisor/work-orders/Create', $this->createFormOptions($customFields));
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

        // The list's New-order modal posts `stay` so the user keeps their page
        // (the new order lands there via the live-synced rows).
        if ($request->boolean('stay')) {
            return back()->with('success', "Work order {$workOrder->order_no} created.");
        }

        return redirect()->route('supervisor.work-orders.index')
            ->with('success', __('Work order :code created.', ['code' => $workOrder->order_no]));
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
                    'workstation_type_id' => $s->workstation_type_id,
                    'duration_minutes' => $s->duration_minutes,
                    'estimated_duration_minutes' => $s->estimated_duration_minutes,
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
            // Pool dispatch (#52): active workstations (with their type) for the
            // per-step "assign workstation" picker, filtered client-side by type.
            'workstations' => \App\Models\Workstation::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'workstation_type_id']),
            'workstationTypeNames' => \App\Models\WorkstationType::pluck('name', 'id'),
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
        return $this->transition($workOrder, 'accept');
    }

    public function reject(WorkOrder $workOrder)
    {
        return $this->transition($workOrder, 'reject');
    }

    public function pause(WorkOrder $workOrder)
    {
        return $this->transition($workOrder, 'pause');
    }

    /**
     * Resume production (#182).
     *
     * Goes through the stop service like every other resume path. Flipping the status
     * here directly would leave an open production stop open forever — blocking the
     * next stop and inflating downtime — and would let an order held for a
     * configuration change restart on the old configuration.
     */
    public function resume(ResumeWorkOrderRequest $request, WorkOrder $workOrder, WorkOrderStopService $stops)
    {
        try {
            $stops->resume($workOrder, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

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
        return $this->transition($workOrder, 'cancel');
    }

    public function reopen(WorkOrder $workOrder)
    {
        return $this->transition($workOrder, 'reopen');
    }

    public function edit(WorkOrder $workOrder)
    {
        return Inertia::render('supervisor/work-orders/Edit', [
            'workOrder' => [
                ...$workOrder->only('id', 'order_no', 'customer_order_no', 'customer_id', 'line_id', 'product_type_id', 'planned_qty', 'unit_price', 'priority', 'description', 'status'),
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
            ],
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'tier']),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $validated = $request->validate([
            'order_no' => 'required|string|max:100|unique:work_orders,order_no,'.$workOrder->id,
            'customer_order_no' => 'nullable|string|max:100',
            'customer_id' => ['nullable', \Illuminate\Validation\Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'line_id' => 'nullable|exists:lines,id',
            'product_type_id' => 'nullable|exists:product_types,id',
            'planned_qty' => 'required|numeric|min:0.01|max:99999999',
            'unit_price' => 'nullable|numeric|min:0|max:99999999',
            'priority' => 'nullable|integer|min:0|max:100',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:PENDING,ACCEPTED,IN_PROGRESS,PAUSED,BLOCKED,DONE,REJECTED,CANCELLED',
        ]);

        $workOrder->update($validated);

        return redirect()->route('supervisor.work-orders.show', $workOrder)
            ->with('success', "Work order {$workOrder->order_no} updated.");
    }

    /**
     * Delete an order that never produced anything. Gated on the `delete work
     * orders` ability (WorkOrderPolicy), which the shipped Supervisor role does
     * not hold — the list only offers this when the current user actually has it.
     */
    public function destroy(WorkOrder $workOrder)
    {
        $this->authorize('delete', $workOrder);

        if ($workOrder->batches()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete a work order that has batches. Cancel it instead.');
        }

        $no = $workOrder->order_no;
        $workOrder->delete();

        return redirect()->route('supervisor.work-orders.index')
            ->with('success', "Work order {$no} deleted.");
    }

    /**
     * Apply one transition to many selected orders (the list's bulk-action bar).
     * Same service call the admin list makes, so a mixed selection is skipped and
     * counted identically in both sections.
     */
    public function bulk(BulkWorkOrderActionRequest $request)
    {
        $message = WorkOrderService::applyBulkTransition(
            $request->validated('ids'),
            $request->validated('action'),
        );

        return redirect()->back()->with('success', $message);
    }

    /**
     * Apply a status transition through the shared table in WorkOrderService, so
     * this list and the admin list can't disagree about what a status allows or
     * word the refusal differently.
     */
    private function transition(WorkOrder $workOrder, string $action)
    {
        $result = WorkOrderService::applyTransition($workOrder, $action);

        return redirect()->back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
