<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreWorkOrderRequest;
use App\Http\Requests\Web\Admin\UpdateWorkOrderRequest;
use App\Models\LabelTemplate;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\CustomFieldService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;
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
        ]);
    }

    public function create(CustomFieldService $customFields)
    {
        return Inertia::render('admin/work-orders/Create', [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customFields' => $customFields->clientConfig('work_order'),
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

        return redirect()->route('admin.work-orders.index')
            ->with('success', "Work order {$workOrder->order_no} created.");
    }

    public function show(WorkOrder $workOrder, CustomFieldService $customFields)
    {
        $workOrder->load(['line', 'productType', 'batches.steps', 'issues.issueType', 'issues.reportedBy']);

        $batches = $workOrder->batches->map(function ($batch) {
            return [
                'id'           => $batch->id,
                'batch_number' => $batch->batch_number,
                'status'       => $batch->status,
                'produced_qty' => $batch->produced_qty,
                'target_qty'   => $batch->target_qty,
                'started_at'   => $batch->started_at?->toISOString(),
                'completed_at' => $batch->completed_at?->toISOString(),
                'released_at'  => $batch->released_at?->toISOString(),
                'steps'        => $batch->steps->map(fn ($s) => [
                    'id'                          => $s->id,
                    'step_number'                 => $s->step_number,
                    'name'                        => $s->name,
                    'status'                      => $s->status,
                    'duration_minutes'            => $s->duration_minutes,
                    'estimated_duration_minutes'  => $s->estimated_duration_minutes ?? null,
                ])->values(),
            ];
        })->values();

        $issues = $workOrder->issues->map(fn ($i) => [
            'id'              => $i->id,
            'title'           => $i->title,
            'status'          => $i->status,
            'issue_type_name' => $i->issueType?->name,
            'is_blocking'     => (bool) ($i->issueType?->is_blocking ?? false),
        ])->values();

        return Inertia::render('admin/work-orders/Show', [
            'workOrder' => [
                'id'               => $workOrder->id,
                'order_no'         => $workOrder->order_no,
                'status'           => $workOrder->status,
                'planned_qty'      => $workOrder->planned_qty,
                'produced_qty'     => $workOrder->produced_qty,
                'priority'         => $workOrder->priority,
                'due_date'         => $workOrder->due_date?->toDateString(),
                'description'      => $workOrder->description,
                'extra_data'       => $workOrder->extra_data,
                'custom_fields'    => $workOrder->custom_fields,
                'process_snapshot' => $workOrder->process_snapshot,
                'created_at'       => $workOrder->created_at->toISOString(),
                'line_name'        => $workOrder->line?->name,
                'product_type_name' => $workOrder->productType?->name,
                'batches'          => $batches,
                'issues'           => $issues,
            ],
            'customFields' => $customFields->clientConfig('work_order'),
        ]);
    }

    public function edit(WorkOrder $workOrder, CustomFieldService $customFields)
    {
        return Inertia::render('admin/work-orders/Edit', [
            'workOrder' => [
                ...$workOrder->only('id', 'order_no', 'line_id', 'product_type_id', 'planned_qty', 'priority', 'description', 'status', 'custom_fields'),
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
            ],
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customFields' => $customFields->clientConfig('work_order'),
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder, CustomFieldService $cf)
    {
        $validated = $request->validated();
        unset($validated['custom_field_files']);

        // Warn when marking as DONE with zero produced quantity
        if (($validated['status'] ?? '') === 'DONE' && (float) $workOrder->produced_qty <= 0) {
            return redirect()->back()->withInput()
                ->with('error', 'Cannot mark as DONE — produced quantity is 0. Register production first or adjust the quantity.');
        }

        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'work_order', $workOrder->custom_fields) ?: null;
        }

        $workOrder->update($validated);

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
