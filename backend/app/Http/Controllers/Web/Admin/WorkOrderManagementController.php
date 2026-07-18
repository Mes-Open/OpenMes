<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreWorkOrderRequest;
use App\Http\Requests\Web\Admin\UpdateWorkOrderRequest;
use App\Models\Customer;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\CustomFieldService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkOrderManagementController extends Controller
{
    public function __construct(protected WorkOrderService $workOrderService) {}

    /**
     * Work order list. Rows live-sync via the `work_orders_all` shape; line and
     * product-type name maps + batch counts come as props.
     */
    public function index()
    {
        $counts = WorkOrder::withCount('batches')
            ->get(['id'])
            ->mapWithKeys(fn ($w) => [$w->id => $w->batches_count]);

        return Inertia::render('admin/work-orders/Index', [
            'counts' => $counts,
            'lineNames' => Line::pluck('name', 'id'),
            'productTypeNames' => ProductType::pluck('name', 'id'),
            'customerNames' => Customer::pluck('name', 'id'),
        ]);
    }

    public function create(CustomFieldService $customFields)
    {
        return Inertia::render('admin/work-orders/Create', [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bomTemplates' => $this->bomTemplateOptions(),
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'tier']),
            'customFields' => $customFields->clientConfig('work_order'),
        ]);
    }

    /**
     * Selectable BOMs (process templates) for the work-order forms - every
     * template a user could pick as a variant/alternative bill of materials,
     * newest version first. The forms scope the picker to the order's product
     * type client-side (each option carries its product_type_id).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function bomTemplateOptions()
    {
        return ProcessTemplate::orderBy('product_type_id')
            ->orderByDesc('version')
            ->get(['id', 'name', 'version', 'is_active', 'product_type_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'version' => $t->version,
                'is_active' => (bool) $t->is_active,
                'product_type_id' => $t->product_type_id,
            ]);
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
            ->with('success', "Work order {$workOrder->order_no} created.");
    }

    public function show(WorkOrder $workOrder, CustomFieldService $customFields)
    {
        $workOrder->load(['customer', 'line', 'productType', 'batches.steps', 'issues.issueType', 'issues.reportedBy']);

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

        return Inertia::render('admin/work-orders/Show', [
            'workOrder' => [
                'id' => $workOrder->id,
                'order_no' => $workOrder->order_no,
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
                'created_at' => $workOrder->created_at->toISOString(),
                'line_name' => $workOrder->line?->name,
                'product_type_name' => $workOrder->productType?->name,
                'batches' => $batches,
                'issues' => $issues,
            ],
            'customFields' => $customFields->clientConfig('work_order'),
        ]);
    }

    public function edit(WorkOrder $workOrder, CustomFieldService $customFields)
    {
        return Inertia::render('admin/work-orders/Edit', [
            'workOrder' => [
                ...$workOrder->only('id', 'order_no', 'customer_order_no', 'customer_id', 'line_id', 'product_type_id', 'planned_qty', 'unit_price', 'priority', 'description', 'status', 'custom_fields'),
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
                // Current BOM selection (empty for legacy single-BOM orders).
                'bom_template_ids' => $workOrder->bomTemplates()->pluck('process_templates.id')->all(),
                // BOMs are frozen once production starts - the form hides the picker.
                'bom_locked' => $workOrder->batches()->exists(),
            ],
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bomTemplates' => $this->bomTemplateOptions(),
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
        if ($workOrder->batches()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete a work order that has batches. Cancel it instead.');
        }

        $no = $workOrder->order_no;
        $workOrder->delete();

        return redirect()->route('admin.work-orders.index')
            ->with('success', "Work order {$no} deleted.");
    }

    public function cancel(WorkOrder $workOrder)
    {
        if (in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()
                ->with('error', 'Cannot cancel a work order that is already in a terminal state.');
        }

        $workOrder->update(['status' => WorkOrder::STATUS_CANCELLED]);

        return redirect()->back()
            ->with('success', "Work order {$workOrder->order_no} cancelled.");
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

    public function reopen(WorkOrder $workOrder)
    {
        if (! in_array($workOrder->status, WorkOrder::TERMINAL_STATUSES)) {
            return redirect()->back()->with('error', 'Only terminal work orders (DONE, REJECTED, CANCELLED) can be reopened.');
        }
        $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        return redirect()->back()->with('success', "Work order {$workOrder->order_no} reopened.");
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
