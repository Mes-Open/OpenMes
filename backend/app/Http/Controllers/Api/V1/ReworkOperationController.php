<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rework\CancelReworkOperationRequest;
use App\Http\Requests\Rework\CompleteReworkOperationRequest;
use App\Http\Requests\Rework\ReworkActionRequest;
use App\Http\Requests\Rework\StoreReworkOperationRequest;
use App\Models\Issue;
use App\Models\ReworkOperation;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Production\StepTimeService;
use App\Services\Quality\ReworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Executable rework routing (#400, item 2): send pieces of a non-conformance
 * back to an earlier operation, run the rework with its own operator and
 * machine time, and return the pieces (accepted or scrapped).
 */
class ReworkOperationController extends Controller
{
    public function __construct(
        private ReworkService $rework,
        private StepTimeService $time,
    ) {}

    /** GET /rework-operations?status=&work_order_id=&batch_id=&issue_id= */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ReworkOperation::query()
            ->whereHas('workOrder', fn ($q) => $q->forUser($user))
            ->with(['sourceStep', 'targetStep']);

        foreach (['work_order_id', 'batch_id', 'issue_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (int) $request->query($filter));
            }
        }
        if ($request->filled('status')) {
            $query->whereIn('status', array_map('trim', explode(',', (string) $request->query('status'))));
        }

        $page = $query->orderByDesc('id')->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn (ReworkOperation $r) => ReworkService::present($r))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** GET /rework-operations/{rework} */
    public function show(Request $request, ReworkOperation $rework): JsonResponse
    {
        $this->authorize('view', $rework->workOrder);

        return $this->respond($rework);
    }

    /** POST /issues/{issue}/rework-operations (Supervisor|Admin) */
    public function store(StoreReworkOperationRequest $request, Issue $issue): JsonResponse
    {
        return $this->attempt(fn () => $this->rework->create($issue, $request->validated(), $request->user()), 201);
    }

    /** POST /rework-operations/{rework}/start */
    public function start(ReworkActionRequest $request, ReworkOperation $rework): JsonResponse
    {
        return $this->attempt(fn () => $this->rework->start($rework, $request->user()));
    }

    /** POST /rework-operations/{rework}/complete */
    public function complete(CompleteReworkOperationRequest $request, ReworkOperation $rework): JsonResponse
    {
        $v = $request->validated();

        return $this->attempt(fn () => $this->rework->complete(
            $rework,
            (float) $v['accepted_qty'],
            (float) $v['scrapped_qty'],
            $request->user(),
            isset($v['scrap_reason_id']) ? (int) $v['scrap_reason_id'] : null,
            $v['notes'] ?? null,
        ));
    }

    /** POST /rework-operations/{rework}/cancel (Supervisor|Admin) */
    public function cancel(CancelReworkOperationRequest $request, ReworkOperation $rework): JsonResponse
    {
        return $this->attempt(fn () => $this->rework->cancel($rework, $request->validated('reason'), $request->user()));
    }

    /**
     * POST /rework-operations/{rework}/time/{kind}/{action} — operator (labor)
     * or machine time on the rework, recorded on the step it reworks.
     */
    public function time(ReworkActionRequest $request, ReworkOperation $rework, string $kind, string $action): JsonResponse
    {
        $step = $rework->targetStep;
        $user = $request->user();
        $workstation = $request->validated('workstation_id') ? Workstation::find($request->validated('workstation_id')) : null;

        try {
            match ("{$kind}:{$action}") {
                'labor:start' => $this->time->startLabor($step, $user, rework: $rework),
                'labor:stop' => $this->time->stopLabor($step, $user, rework: $rework),
                'machine:start' => $this->time->startMachine($step, $workstation, $user, rework: $rework),
                'machine:stop' => $this->time->stopMachine($step, $workstation, $user, rework: $rework),
            };
        } catch (\DomainException $e) {
            return $this->error($e);
        }

        return $this->respond($rework->fresh());
    }

    /** GET /work-orders/{workOrder}/rework-operations */
    public function forWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        $reworks = ReworkOperation::where('work_order_id', $workOrder->id)->with(['sourceStep', 'targetStep'])->orderBy('id')->get();

        return response()->json(['data' => $reworks->map(fn (ReworkOperation $r) => ReworkService::present($r))->values()]);
    }

    private function attempt(callable $fn, int $status = 200): JsonResponse
    {
        try {
            $rework = $fn();
        } catch (\DomainException $e) {
            return $this->error($e);
        }

        return $this->respond($rework, $status);
    }

    private function respond(ReworkOperation $rework, int $status = 200): JsonResponse
    {
        $rework->load(['sourceStep', 'targetStep', 'timeEntries.user:id,name', 'timeEntries.workstation:id,code,name']);

        return response()->json([
            'data' => ReworkService::present($rework) + [
                'live_totals' => $this->time->liveReworkTotals($rework),
                'time_entries' => $rework->timeEntries->sortBy('started_at')->map(fn ($e) => BatchStepTimeController::entry($e))->values(),
            ],
        ], $status);
    }

    private function error(\DomainException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'errors' => ['rework' => [$e->getMessage()]],
        ], 422);
    }
}
