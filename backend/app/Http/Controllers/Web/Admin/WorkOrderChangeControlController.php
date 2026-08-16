<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\ApplyChangeRequestRequest;
use App\Http\Requests\WorkOrder\RejectChangeRequestRequest;
use App\Http\Requests\WorkOrder\StopWorkOrderRequest;
use App\Http\Requests\WorkOrder\StoreChangeRequestRequest;
use App\Http\Requests\WorkOrder\UpdateChangeRequestRequest;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductRevision;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Services\WorkOrder\ChangeRequestService;
use App\Services\WorkOrder\WorkOrderStopService;
use Inertia\Inertia;

/**
 * Change control in the admin UI (#182).
 *
 * The same services the REST API drives, wrapped in Inertia redirects so the
 * work-order page behaves like the rest of the admin panel: a refused domain rule
 * comes back as an error flash rather than a JSON body.
 */
class WorkOrderChangeControlController extends Controller
{
    public function __construct(
        protected WorkOrderStopService $stops,
        protected ChangeRequestService $changeRequests,
    ) {}

    public function stop(StopWorkOrderRequest $request, WorkOrder $workOrder)
    {
        $this->authorize('update', $workOrder);

        try {
            $this->stops->stop($workOrder, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Production stopped for :order.', ['order' => $workOrder->order_no]));
    }

    public function storeChangeRequest(StoreChangeRequestRequest $request, WorkOrder $workOrder)
    {
        $this->authorize('create', WorkOrderChangeRequest::class);
        $this->authorize('update', $workOrder);

        try {
            $changeRequest = $this->changeRequests->create($workOrder, $request->validated(), $request->user());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.work-order-change-requests.show', $changeRequest)
            ->with('success', __('Change request :code created.', ['code' => $changeRequest->code]));
    }

    /**
     * The review screen: the request, its diff, the impact analysis as it stands now,
     * and what the current user is allowed to do with it.
     */
    public function show(WorkOrderChangeRequest $changeRequest)
    {
        $this->authorize('view', $changeRequest);

        $changeRequest->load([
            // Loaded whole rather than column-selected: the live impact analysis reads
            // product_type_id, product_revision_id, process_snapshot and extra_data as
            // well, and a select list that misses one of them makes the analysis
            // compute against null instead of failing loudly. The response below only
            // exposes the handful of fields the page needs.
            'workOrder',
            'requestedBy:id,name', 'approvedBy:id,name', 'rejectedBy:id,name', 'appliedBy:id,name',
            'stop:id,type,reason,stopped_at,requires_change',
            'resultingSnapshot:id,change_request_id,version,effective_from,effective_from_qty',
        ]);

        $user = request()->user();

        return Inertia::render('admin/work-orders/ChangeRequest', [
            'changeRequest' => [
                ...$changeRequest->only([
                    'id', 'code', 'title', 'reason', 'proposed', 'impact',
                    'produced_disposition', 'material_disposition', 'implementation_notes',
                    'rejection_reason', 'resulting_snapshot_version',
                ]),
                'status' => $changeRequest->status->value,
                'status_label' => $changeRequest->status->label(),
                'effective_from' => $changeRequest->effective_from->value,
                'effective_from_label' => $changeRequest->effective_from->label(),
                'work_order' => $changeRequest->workOrder?->only(
                    'id', 'order_no', 'status', 'planned_qty', 'produced_qty', 'snapshot_version'
                ),
                'stop' => $changeRequest->stop === null ? null : [
                    ...$changeRequest->stop->only('id', 'reason', 'requires_change'),
                    'type' => $changeRequest->stop->type->value,
                    'type_label' => $changeRequest->stop->type->label(),
                    'stopped_at' => $changeRequest->stop->stopped_at?->toISOString(),
                ],
                'requested_by' => $changeRequest->requestedBy?->name,
                'approved_by' => $changeRequest->approvedBy?->name,
                'rejected_by' => $changeRequest->rejectedBy?->name,
                'applied_by' => $changeRequest->appliedBy?->name,
                'submitted_at' => $changeRequest->submitted_at?->toISOString(),
                'approved_at' => $changeRequest->approved_at?->toISOString(),
                'rejected_at' => $changeRequest->rejected_at?->toISOString(),
                'applied_at' => $changeRequest->applied_at?->toISOString(),
                // Recomputed live: the stored impact is frozen as the approver saw it,
                // but a reviewer opening the page now needs the current picture. Null
                // when the order behind the request has been deleted — the page still
                // has to render the request's own history.
                'live_impact' => $changeRequest->workOrder === null
                    ? null
                    : $this->changeRequests->impactFor($changeRequest),
                'diff' => $changeRequest->diff(),
            ],
            'can' => [
                'update' => $user->can('update', $changeRequest),
                'submit' => $user->can('submit', $changeRequest),
                'approve' => $user->can('approve', $changeRequest),
                'apply' => $user->can('apply', $changeRequest),
                'cancel' => $user->can('cancel', $changeRequest),
            ],
        ]);
    }

    public function updateChangeRequest(UpdateChangeRequestRequest $request, WorkOrderChangeRequest $changeRequest)
    {
        $this->authorize('update', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->update($changeRequest, $request->validated(), $request->user()),
            __('Change request updated.'),
        );
    }

    public function submit(WorkOrderChangeRequest $changeRequest)
    {
        $this->authorize('submit', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->submit($changeRequest, request()->user()),
            __('Change request submitted for review.'),
        );
    }

    public function approve(WorkOrderChangeRequest $changeRequest)
    {
        $this->authorize('approve', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->approve($changeRequest, request()->user()),
            __('Change request approved.'),
        );
    }

    public function reject(RejectChangeRequestRequest $request, WorkOrderChangeRequest $changeRequest)
    {
        $this->authorize('reject', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->reject($changeRequest, $request->user(), $request->validated()['reason']),
            __('Change request rejected.'),
        );
    }

    public function cancel(WorkOrderChangeRequest $changeRequest)
    {
        $this->authorize('cancel', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->cancel($changeRequest, request()->user()),
            __('Change request cancelled.'),
        );
    }

    public function apply(ApplyChangeRequestRequest $request, WorkOrderChangeRequest $changeRequest)
    {
        $this->authorize('apply', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->apply($changeRequest, $request->user(), $request->validated()),
            __('Change request applied. The work order is now on a new configuration version.'),
        );
    }

    /**
     * Options a stop or change-request form needs, loaded with the work-order page so
     * opening a modal never waits on a round trip.
     *
     * @return array<string, mixed>
     */
    public static function formOptions(WorkOrder $workOrder): array
    {
        return [
            'downtimeReasons' => DowntimeReason::orderBy('name')->get(['id', 'name']),
            // A change request may propose a different line, so the review and the
            // request form both need the list to offer.
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productRevisions' => ProductRevision::where('product_type_id', $workOrder->product_type_id)
                ->orderBy('revision_code')
                ->get(['id', 'revision_code', 'lifecycle_status']),
            'bomTemplates' => ProcessTemplate::where('product_type_id', $workOrder->product_type_id)
                ->orderBy('name')
                ->get(['id', 'name', 'version']),
            // What each proposable field is set to right now, so the form can seed a
            // field the moment it is ticked. A ticked field left at its current value
            // is still a deliberate proposal, and has to submit as one.
            'current' => [
                'product_revision_id' => $workOrder->product_revision_id,
                'planned_qty' => $workOrder->planned_qty,
                'line_id' => $workOrder->line_id,
                'bom_template_ids' => $workOrder->bomTemplates()->pluck('process_templates.id')->all(),
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
                'description' => $workOrder->description,
                // No column of its own — notes live in extra_data, the same place
                // ChangeRequestService writes them back to.
                'production_notes' => $workOrder->extra_data['production_notes'] ?? null,
            ],
        ];
    }

    /** Turn a refused domain rule into an error flash instead of a 500. */
    private function run(\Closure $action, string $message)
    {
        try {
            $action();
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
