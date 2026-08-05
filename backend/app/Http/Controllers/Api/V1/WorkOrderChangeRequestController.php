<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\ApplyChangeRequestRequest;
use App\Http\Requests\WorkOrder\RejectChangeRequestRequest;
use App\Http\Requests\WorkOrder\StoreChangeRequestRequest;
use App\Http\Requests\WorkOrder\UpdateChangeRequestRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Services\WorkOrder\ChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The controlled change workflow over HTTP (#182).
 *
 * Every transition is its own endpoint rather than a status field on PATCH, because
 * each one is a different authorization decision: authoring, reviewing and applying a
 * change are deliberately not the same right.
 */
class WorkOrderChangeRequestController extends Controller
{
    /** Relations every response loads, so a client never has to follow up for the audit trail. */
    private const RELATIONS = [
        'requestedBy:id,name',
        'approvedBy:id,name',
        'rejectedBy:id,name',
        'appliedBy:id,name',
        'stop:id,type,stopped_at',
        'resultingSnapshot:id,change_request_id,version,effective_from,effective_from_qty',
    ];

    public function __construct(
        protected ChangeRequestService $changeRequests,
    ) {}

    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        return response()->json([
            'data' => $workOrder->changeRequests()->with(self::RELATIONS)->get(),
        ]);
    }

    public function store(StoreChangeRequestRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('create', WorkOrderChangeRequest::class);
        $this->authorize('update', $workOrder);

        try {
            $changeRequest = $this->changeRequests->create($workOrder, $request->validated(), $request->user());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Change request created.',
            'data' => $changeRequest->load(self::RELATIONS),
        ], 201);
    }

    public function show(WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('view', $changeRequest);

        return response()->json([
            'data' => $changeRequest->load(self::RELATIONS),
            // The field-by-field view the UI renders, so the diff is computed in one
            // place rather than reimplemented per client.
            'diff' => $changeRequest->diff(),
        ]);
    }

    public function update(UpdateChangeRequestRequest $request, WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('update', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->update($changeRequest, $request->validated(), $request->user()),
            'Change request updated.',
        );
    }

    public function submit(Request $request, WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('submit', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->submit($changeRequest, $request->user()),
            'Change request submitted for review.',
        );
    }

    public function approve(Request $request, WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('approve', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->approve($changeRequest, $request->user()),
            'Change request approved.',
        );
    }

    public function reject(RejectChangeRequestRequest $request, WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('reject', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->reject($changeRequest, $request->user(), $request->validated()['reason']),
            'Change request rejected.',
        );
    }

    public function cancel(Request $request, WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('cancel', $changeRequest);

        return $this->run(
            fn () => $this->changeRequests->cancel($changeRequest, $request->user()),
            'Change request cancelled.',
        );
    }

    public function apply(ApplyChangeRequestRequest $request, WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('apply', $changeRequest);

        $response = $this->run(
            fn () => $this->changeRequests->apply($changeRequest, $request->user(), $request->validated()),
            'Change request applied.',
        );

        if ($response->getStatusCode() === 200) {
            $response->setData(array_merge((array) $response->getData(true), [
                'work_order' => $changeRequest->workOrder()->first(),
            ]));
        }

        return $response;
    }

    /**
     * Impact analysis for a request as it stands — the picture an approver should see
     * now, not the one frozen when the draft was written.
     */
    public function impact(WorkOrderChangeRequest $changeRequest): JsonResponse
    {
        $this->authorize('view', $changeRequest);

        return response()->json([
            'data' => $this->changeRequests->analyzeImpact($changeRequest->workOrder, $changeRequest->proposedChanges()),
        ]);
    }

    /**
     * Run a workflow transition, turning a refused domain rule into a 422 with the
     * reason the shop floor needs to read.
     */
    private function run(\Closure $action, string $message): JsonResponse
    {
        try {
            $changeRequest = $action();
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $message,
            'data' => $changeRequest->load(self::RELATIONS),
        ]);
    }
}
