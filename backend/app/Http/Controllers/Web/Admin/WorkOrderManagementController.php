<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\BuildsWorkOrderFormOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReclassifyClassRequest;
use App\Http\Requests\Api\V1\RecordConsumptionRequest;
use App\Http\Requests\Api\V1\ReturnAllocationRequest;
use App\Http\Requests\Web\Admin\BulkWorkOrderActionRequest;
use App\Http\Requests\Web\Admin\StoreWorkOrderRequest;
use App\Http\Requests\Web\Admin\UpdateWorkOrderRequest;
use App\Http\Requests\WorkOrder\ResumeWorkOrderRequest;
use App\Models\Customer;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\CustomFieldService;
use App\Services\Material\MaterialAllocationService;
use App\Services\Material\MaterialReclassificationService;
use App\Services\WorkOrder\WorkOrderService;
use App\Services\WorkOrder\WorkOrderStopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkOrderManagementController extends Controller
{
    use BuildsWorkOrderFormOptions;

    /**
     * Entries in the detail page's activity panel. A finished order has one per
     * step per batch, which is a scroll, not a summary — the panel answers "what
     * happened lately", and the batch list below it holds the rest.
     */
    private const ACTIVITY_LIMIT = 8;

    public function __construct(protected WorkOrderService $workOrderService) {}

    /**
     * Work order list. Rows live-sync via the `work_orders_all` shape; line and
     * product-type name maps + batch counts come as props.
     */
    public function index(CustomFieldService $customFields)
    {
        $counts = WorkOrder::withCount('batches')
            ->get(['id'])
            ->mapWithKeys(fn ($w) => [$w->id => $w->batches_count]);

        return Inertia::render('admin/work-orders/Index', [
            'counts' => $counts,
            // Name maps cover every line/product/customer an existing row might
            // reference, including deactivated ones — unlike the create-form
            // options below, which only offer what is still selectable.
            'lineNames' => Line::pluck('name', 'id'),
            'productTypeNames' => ProductType::pluck('name', 'id'),
            'customerNames' => Customer::pluck('name', 'id'),
            // Feeds the list's "New work order" modal, which renders the same form
            // as the create page.
            ...$this->createFormOptions($customFields),
        ]);
    }

    public function create(CustomFieldService $customFields)
    {
        return Inertia::render('admin/work-orders/Create', $this->createFormOptions($customFields));
    }

    public function store(StoreWorkOrderRequest $request, CustomFieldService $cf)
    {
        $validated = $request->validated();
        unset($validated['custom_field_files']);

        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'work_order') ?: null;
        }

        try {
            $workOrder = $this->workOrderService->createWorkOrder($validated);
        } catch (\Exception $e) {
            report($e);

            return back()->withInput()
                ->with('error', __('Failed to create work order. Please check your input and try again.'));
        }

        // The planner's New-order modal posts `stay` so the user keeps their
        // page (the new order lands there via the refreshed props).
        if ($request->boolean('stay')) {
            return back()->with('success', "Work order {$workOrder->order_no} created.");
        }

        return redirect()->route('admin.work-orders.index')
            ->with('success', __('Work order :code created.', ['code' => $workOrder->order_no]));
    }

    public function show(WorkOrder $workOrder, CustomFieldService $customFields)
    {
        $workOrder->load(['customer', 'line', 'productType', 'batches.steps.completedBy', 'issues.issueType', 'issues.reportedBy']);

        $batches = $workOrder->batches->map(function ($batch) {
            return [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'status' => $batch->status,
                'produced_qty' => $batch->produced_qty,
                'target_qty' => $batch->target_qty,
                'started_at' => $batch->started_at?->toISOString(),
                'completed_at' => $batch->completed_at?->toISOString(),
                'released_at' => $batch->released_at?->toISOString(),
                'steps' => $batch->steps->map(fn ($s) => [
                    'id' => $s->id,
                    'step_number' => $s->step_number,
                    'name' => $s->name,
                    'status' => $s->status,
                    'duration_minutes' => $s->duration_minutes,
                    'estimated_duration_minutes' => $s->estimated_duration_minutes ?? null,
                    'started_at' => $s->started_at?->toISOString(),
                    'completed_at' => $s->completed_at?->toISOString(),
                ])->values(),
            ];
        })->values();

        $issues = $workOrder->issues->map(fn ($i) => [
            'id' => $i->id,
            'title' => $i->title,
            'status' => $i->status,
            'issue_type_name' => $i->issueType?->name,
            'is_blocking' => (bool) ($i->issueType?->is_blocking ?? false),
            'reported_at' => $i->reported_at?->toISOString(),
            'reported_by' => $i->reportedBy?->name,
        ])->values();

        // Materials reconciliation (#99): the allocations pulled for this order, so
        // the page can offer record-consumption / return / reclassify per material.
        $workOrder->load(['allocations.material', 'allocations.lotPicks.lot']);
        $allocations = $workOrder->allocations->map(fn ($a) => [
            'id' => $a->id,
            'material_id' => $a->material_id,
            'material_code' => $a->material?->code,
            'material_name' => $a->material?->name,
            'unit_of_measure' => $a->material?->unit_of_measure,
            'status' => $a->status,
            'allocated_qty' => (float) $a->allocated_qty,
            'consumed_qty' => (float) $a->consumed_qty,
            'scrap_qty' => (float) $a->scrap_qty,
            'returned_qty' => (float) $a->returned_qty,
            'lots' => $a->lotPicks->map(fn ($p) => [
                'lot_id' => $p->material_lot_id,
                'lot_number' => $p->lot?->lot_number,
                'picked_qty' => (float) $p->picked_qty,
            ])->values(),
        ])->values();

        // Change control (#182): the stop history with durations, and every change
        // request raised against this order.
        $stops = $workOrder->stops()->with(['stoppedBy:id,name', 'resumedBy:id,name'])->get()
            ->map(fn ($stop) => [
                'id' => $stop->id,
                'type' => $stop->type->value,
                'type_label' => $stop->type->label(),
                'reason' => $stop->reason,
                'requires_change' => (bool) $stop->requires_change,
                'produced_qty_at_stop' => $stop->produced_qty_at_stop,
                'snapshot_version_at_stop' => $stop->snapshot_version_at_stop,
                'stopped_at' => $stop->stopped_at?->toISOString(),
                'resumed_at' => $stop->resumed_at?->toISOString(),
                'resume_notes' => $stop->resume_notes,
                'duration_minutes' => $stop->durationMinutes(),
                'is_open' => $stop->isOpen(),
                'stopped_by' => $stop->stoppedBy?->name,
                'resumed_by' => $stop->resumedBy?->name,
            ])->values();

        $changeRequests = $workOrder->changeRequests()->with('requestedBy:id,name')->get()
            ->map(fn ($cr) => [
                'id' => $cr->id,
                'code' => $cr->code,
                'title' => $cr->title,
                'status' => $cr->status->value,
                'status_label' => $cr->status->label(),
                'effective_from_label' => $cr->effective_from->label(),
                'resulting_snapshot_version' => $cr->resulting_snapshot_version,
                'requested_by' => $cr->requestedBy?->name,
                'created_at' => $cr->created_at?->toISOString(),
            ])->values();

        $canReclassify = (bool) request()->user()?->hasAnyRole(['Supervisor', 'Admin']);

        $openStop = $workOrder->openStop();
        // An order held for a change may only resume once one has been applied — the
        // page needs to know which, so Resume can carry it.
        $appliedChangeRequest = $workOrder->changeRequests()
            ->where('status', \App\Enums\ChangeRequestStatus::Applied->value)
            ->when($openStop, fn ($q) => $q->where('applied_at', '>=', $openStop->stopped_at))
            ->reorder('applied_at', 'desc')
            ->first();

        return Inertia::render('admin/work-orders/Show', [
            'stops' => $stops,
            'changeRequests' => $changeRequests,
            'changeControl' => [
                'open_stop_id' => $openStop?->id,
                'requires_change' => (bool) $openStop?->requires_change || $workOrder->isOnChangeHold(),
                'applied_change_request_id' => $appliedChangeRequest?->id,
                'stop_types' => collect(\App\Enums\WorkOrderStopType::cases())
                    ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values(),
                'effective_points' => collect(\App\Enums\ChangeEffectivePoint::cases())
                    ->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()])->values(),
                'can_raise_change' => request()->user()->can('create', \App\Models\WorkOrderChangeRequest::class),
                ...WorkOrderChangeControlController::formOptions($workOrder),
            ],
            'workOrder' => [
                'id' => $workOrder->id,
                'order_no' => $workOrder->order_no,
                'snapshot_version' => $workOrder->snapshot_version,
                'customer_order_no' => $workOrder->customer_order_no,
                'customer_name' => $workOrder->customer?->name,
                'customer_tier' => $workOrder->customer?->tier?->value,
                'status' => $workOrder->status,
                'planned_qty' => $workOrder->planned_qty,
                'unit_price' => $workOrder->unit_price,
                'produced_qty' => $workOrder->produced_qty,
                'priority' => $workOrder->priority,
                'priority_score' => $workOrder->priority_score,
                'due_date' => $workOrder->due_date?->toDateString(),
                'description' => $workOrder->description,
                'extra_data' => $workOrder->extra_data,
                'custom_fields' => $workOrder->custom_fields,
                'process_snapshot' => $workOrder->process_snapshot,
                'estimated_standard_production_minutes' => $workOrder->estimatedStandardProductionMinutes(),
                'created_at' => $workOrder->created_at->toISOString(),
                'completed_at' => $workOrder->completed_at?->toISOString(),
                'line_name' => $workOrder->line?->name,
                'product_type_name' => $workOrder->productType?->name,
                'batches' => $batches,
                'issues' => $issues,
                'activity' => $this->activityFeed($workOrder),
                'allocations' => $allocations,
            ],
            'canReclassify' => $canReclassify,
            'materials' => $canReclassify
                ? Material::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                : [],
            'customFields' => $customFields->clientConfig('work_order'),
        ]);
    }

    /**
     * What has actually happened to this order, newest first.
     *
     * Assembled from the timestamps the records already carry rather than from
     * an event log: there is no audit trail on work orders, and inventing one
     * for a sidebar panel would mean writing a row on every transition. Every
     * entry here is a fact some column states — nothing is inferred, so an entry
     * is missing rather than approximate when a timestamp was never recorded.
     *
     * @return array<int, array{title: string, meta: ?string, at: string, tone: string}>
     */
    private function activityFeed(WorkOrder $workOrder): array
    {
        $events = [];

        $events[] = [
            'title' => __('Order created'),
            'meta' => implode(' · ', array_filter([
                $workOrder->productType?->name,
                __(':count pcs', ['count' => (float) $workOrder->planned_qty]),
                $workOrder->line?->name,
            ])),
            'at' => $workOrder->created_at->toISOString(),
            'tone' => 'muted',
        ];

        foreach ($workOrder->batches as $batch) {
            if ($batch->started_at) {
                $events[] = [
                    'title' => __('Batch #:number started', ['number' => $batch->batch_number]),
                    'meta' => __(':count pcs', ['count' => (float) $batch->target_qty]),
                    'at' => $batch->started_at->toISOString(),
                    'tone' => 'accent',
                ];
            }

            foreach ($batch->steps as $step) {
                if (! $step->completed_at) {
                    continue;
                }

                $events[] = [
                    'title' => __(':step completed', ['step' => $step->name]),
                    'meta' => implode(' · ', array_filter([
                        __('Batch #:number', ['number' => $batch->batch_number]),
                        __('step :n/:total', ['n' => $step->step_number, 'total' => $batch->steps->count()]),
                        $step->completedBy?->name,
                    ])),
                    'at' => $step->completed_at->toISOString(),
                    'tone' => 'running',
                ];
            }

            if ($batch->completed_at) {
                $events[] = [
                    'title' => __('Batch #:number completed', ['number' => $batch->batch_number]),
                    'meta' => __(':count pcs', ['count' => (float) $batch->produced_qty]),
                    'at' => $batch->completed_at->toISOString(),
                    'tone' => 'running',
                ];
            }
        }

        foreach ($workOrder->issues as $issue) {
            if (! $issue->reported_at) {
                continue;
            }

            $events[] = [
                'title' => __('Issue reported: :title', ['title' => $issue->title]),
                'meta' => implode(' · ', array_filter([$issue->issueType?->name, $issue->reportedBy?->name])),
                'at' => $issue->reported_at->toISOString(),
                'tone' => 'blocked',
            ];
        }

        if ($workOrder->completed_at) {
            $events[] = [
                'title' => __('Order completed'),
                'meta' => __(':produced / :planned pcs', [
                    'produced' => (float) $workOrder->produced_qty,
                    'planned' => (float) $workOrder->planned_qty,
                ]),
                'at' => $workOrder->completed_at->toISOString(),
                'tone' => 'running',
            ];
        }

        usort($events, fn ($a, $b) => strcmp($b['at'], $a['at']));

        return array_slice($events, 0, self::ACTIVITY_LIMIT);
    }

    /**
     * Materials reconciliation (#99): declare actual consumption, return unused
     * material to stock, and reclassify a quantity to another class. Each
     * allocation must belong to this work order.
     */
    public function recordConsumption(RecordConsumptionRequest $request, WorkOrder $workOrder, MaterialAllocation $allocation, MaterialAllocationService $allocations)
    {
        $this->assertAllocationBelongs($workOrder, $allocation);

        try {
            $allocations->recordConsumption(
                $allocation,
                (float) $request->validated('consumed_qty'),
                (float) ($request->validated('scrap_qty') ?? 0),
                $request->validated('notes'),
            );

            return back()->with('success', __('Consumption recorded'));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function returnAllocation(ReturnAllocationRequest $request, WorkOrder $workOrder, MaterialAllocation $allocation, MaterialAllocationService $allocations)
    {
        $this->assertAllocationBelongs($workOrder, $allocation);

        try {
            $allocations->returnQuantity(
                $allocation,
                (float) $request->validated('qty'),
                $request->user(),
                $request->validated('reason'),
            );

            return back()->with('success', __('Material returned to stock'));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reclassify(ReclassifyClassRequest $request, WorkOrder $workOrder, MaterialReclassificationService $reclassifications)
    {
        try {
            $source = Material::findOrFail($request->validated('source_material_id'));

            // The panel always reclassifies one of this order's pulled materials —
            // enforce that so the nested route is a real scope, not decoration.
            if (! $workOrder->allocations()->where('material_id', $source->id)->exists()) {
                abort(404);
            }

            $target = Material::findOrFail($request->validated('target_material_id'));
            $lot = $request->validated('source_lot_id') ? MaterialLot::findOrFail($request->validated('source_lot_id')) : null;

            $reclassifications->reclassifyClass(
                $source,
                $target,
                (float) $request->validated('qty'),
                $request->user(),
                $lot,
                $request->validated('reason'),
            );

            return back()->with('success', __('Material reclassified'));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function assertAllocationBelongs(WorkOrder $workOrder, MaterialAllocation $allocation): void
    {
        if ($allocation->batch?->work_order_id !== $workOrder->id) {
            abort(404);
        }
    }

    public function edit(WorkOrder $workOrder, CustomFieldService $customFields)
    {
        return Inertia::render('admin/work-orders/Edit', [
            'workOrder' => [
                ...$workOrder->only('id', 'order_no', 'customer_order_no', 'customer_id', 'line_id', 'product_type_id', 'product_revision_id', 'planned_qty', 'unit_price', 'counting_source', 'priority', 'description', 'status', 'custom_fields'),
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
                // Current BOM selection (empty for legacy single-BOM orders).
                'bom_template_ids' => $workOrder->bomTemplates()->pluck('process_templates.id')->all(),
                // BOMs are frozen once production starts - the form hides the picker.
                'bom_locked' => $workOrder->batches()->exists(),
            ],
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bomTemplates' => $this->bomTemplateOptions(),
            'productRevisions' => $this->productRevisionOptions(),
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'tier']),
            'customFields' => $customFields->clientConfig('work_order'),
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder, CustomFieldService $cf)
    {
        $validated = $request->validated();
        unset($validated['custom_field_files']);

        // BOM selection is not a column - pull it out and apply via the service.
        $bomTemplateIds = $validated['bom_template_ids'] ?? null;
        unset($validated['bom_template_ids']);

        // Warn when marking as DONE with zero produced quantity
        if (($validated['status'] ?? '') === 'DONE' && (float) $workOrder->produced_qty <= 0) {
            return redirect()->back()->withInput()
                ->with('error', 'Cannot mark as DONE — produced quantity is 0. Register production first or adjust the quantity.');
        }

        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'work_order', $workOrder->custom_fields) ?: null;
        }

        // priority is NOT NULL DEFAULT 0; a cleared field arrives as null. The
        // store path coerces via WorkOrderService — preserve the existing value
        // here rather than passing an explicit null.
        $validated['priority'] ??= $workOrder->priority;

        // Apply the BOM re-selection only when it actually changed, so unchanged
        // submits don't rebuild the snapshot or trip the "production started" guard.
        // A product-type change is itself a BOM change: the old snapshot/pivot no
        // longer belongs to the order, so rebuild (from the submitted selection, or
        // the new type's auto-picked BOM when none was submitted).
        $productTypeChanged = array_key_exists('product_type_id', $validated)
            && (int) $validated['product_type_id'] !== (int) $workOrder->product_type_id;

        $requested = null;
        if ($bomTemplateIds !== null) {
            $current = $workOrder->bomTemplates()->pluck('process_templates.id')->all();
            $normalized = array_values(array_unique(array_map('intval', $bomTemplateIds)));
            if ($current !== $normalized || $productTypeChanged) {
                $requested = $normalized;
            }
        } elseif ($productTypeChanged) {
            $requested = [];
        }

        // Reject a BOM change on a started order before touching anything, so the
        // field edits aren't half-saved alongside a rejected BOM change.
        if ($requested !== null && $workOrder->batches()->exists()) {
            return redirect()->back()->withInput()
                ->with('error', 'Cannot change BOMs after production has started.');
        }

        // A product revision (#180) may be changed freely before production, but
        // once batches exist the change must go through the controlled change
        // workflow (#182) — reject it here to keep the as-built revision honest.
        $revisionChanged = array_key_exists('product_revision_id', $validated)
            && (int) $validated['product_revision_id'] !== (int) $workOrder->product_revision_id;
        if ($revisionChanged && $workOrder->batches()->exists()) {
            return redirect()->back()->withInput()
                ->with('error', 'Cannot change the product revision after production has started.');
        }

        // Field edits and the BOM re-selection commit together (or not at all).
        try {
            DB::transaction(function () use ($workOrder, $validated, $requested) {
                $workOrder->update($validated);
                if ($requested !== null) {
                    $this->workOrderService->updateBomSelection($workOrder, $requested);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update work order. Please check your input and try again.');
        }

        return redirect()->route('admin.work-orders.index')
            ->with('success', "Work order {$workOrder->order_no} updated.");
    }

    public function destroy(WorkOrder $workOrder)
    {
        // Not Admin-only any more: supervisors reach these pages for change
        // control (#182), and they hold `edit work orders` but not `delete work
        // orders`. The tab gate answers "may you open this section", not "may
        // you destroy this row" — the supervisor twin has always checked the
        // policy here, and this one has to as well.
        $this->authorize('delete', $workOrder);

        if ($workOrder->batches()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete a work order that has batches. Cancel it instead.');
        }

        $no = $workOrder->order_no;
        $workOrder->delete();

        return redirect()->route('admin.work-orders.index')
            ->with('success', "Work order {$no} deleted.");
    }

    /** Apply one transition to one order, or bounce back with its refusal message. */
    private function transition(WorkOrder $workOrder, string $action)
    {
        $result = WorkOrderService::applyTransition($workOrder, $action);

        return redirect()->back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Apply one transition to many selected orders (the list's bulk-action bar).
     * The skip-the-ineligible rules live in the service, shared with the
     * supervisor list so the two can't drift.
     */
    public function bulk(BulkWorkOrderActionRequest $request)
    {
        $message = WorkOrderService::applyBulkTransition(
            $request->validated('ids'),
            $request->validated('action'),
        );

        return redirect()->back()->with('success', $message);
    }

    public function cancel(WorkOrder $workOrder)
    {
        return $this->transition($workOrder, 'cancel');
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
     * Goes through the stop service so a structured stop is closed with its duration
     * and the change-hold gate is enforced. An order paused the simple way has no stop
     * record and resumes exactly as it did before.
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

    public function reopen(WorkOrder $workOrder)
    {
        return $this->transition($workOrder, 'reopen');
    }

    public function complete(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
            return redirect()->back()->with('error', 'Only IN_PROGRESS work orders can be completed.');
        }

        $validated = $request->validate([
            'produced_qty' => 'required|numeric|min:0.01',
        ]);

        $workOrder->update([
            'status' => WorkOrder::STATUS_DONE,
            'produced_qty' => $validated['produced_qty'],
            'completed_at' => now(),
        ]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} completed with {$validated['produced_qty']} produced.");
    }
}
