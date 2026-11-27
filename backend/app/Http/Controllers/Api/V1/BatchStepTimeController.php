<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StepTime\RecordStepTimeRequest;
use App\Http\Requests\StepTime\StoreManualStepTimeRequest;
use App\Models\BatchStep;
use App\Models\BatchStepTimeEntry;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Production\StepTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Independent operator (labor) and machine time per batch step (#400, item 3).
 *
 * Operators clock themselves on and off a running step and start / stop its
 * machine time; supervisors enter or remove time afterwards. Each step's
 * de-duplicated totals and the work order's per-operation times are readable
 * for costing and reporting.
 */
class BatchStepTimeController extends Controller
{
    public function __construct(private StepTimeService $time) {}

    /** GET /batch-steps/{batchStep}/time-entries */
    public function index(Request $request, BatchStep $batchStep): JsonResponse
    {
        $this->authorize('view', $batchStep->batch->workOrder);

        $batchStep->load(['timeEntries' => fn ($q) => $q->with(['user:id,name', 'workstation:id,code,name'])->orderBy('started_at')]);

        return response()->json([
            'data' => $batchStep->timeEntries->map(fn (BatchStepTimeEntry $e) => self::entry($e))->values(),
            'totals' => $this->time->liveTotals($batchStep) + [
                'recorded_labor_minutes' => $batchStep->actual_labor_minutes,
                'recorded_machine_minutes' => $batchStep->actual_machine_minutes,
            ],
        ]);
    }

    /** POST /batch-steps/{batchStep}/time-entries/labor/start — clock the caller on. */
    public function startLabor(RecordStepTimeRequest $request, BatchStep $batchStep): JsonResponse
    {
        return $this->respond(fn () => $this->time->startLabor($batchStep, $request->user()), 201);
    }

    /** POST /batch-steps/{batchStep}/time-entries/labor/stop — clock the caller off. */
    public function stopLabor(RecordStepTimeRequest $request, BatchStep $batchStep): JsonResponse
    {
        return $this->respond(fn () => $this->time->stopLabor($batchStep, $request->user()));
    }

    /** POST /batch-steps/{batchStep}/time-entries/machine/start */
    public function startMachine(RecordStepTimeRequest $request, BatchStep $batchStep): JsonResponse
    {
        $workstation = $request->validated('workstation_id') ? Workstation::find($request->validated('workstation_id')) : null;

        return $this->respond(fn () => $this->time->startMachine($batchStep, $workstation, $request->user()), 201);
    }

    /** POST /batch-steps/{batchStep}/time-entries/machine/stop */
    public function stopMachine(RecordStepTimeRequest $request, BatchStep $batchStep): JsonResponse
    {
        $workstation = $request->validated('workstation_id') ? Workstation::find($request->validated('workstation_id')) : null;

        try {
            $closed = $this->time->stopMachine($batchStep, $workstation, $request->user());
        } catch (\DomainException $e) {
            return $this->error($e);
        }

        return response()->json([
            'message' => __('Machine time stopped.'),
            'data' => $closed->map(fn (BatchStepTimeEntry $e) => self::entry($e->load(['user:id,name', 'workstation:id,code,name'])))->values(),
        ]);
    }

    /** POST /batch-steps/{batchStep}/time-entries — supervisor enters time afterwards. */
    public function store(StoreManualStepTimeRequest $request, BatchStep $batchStep): JsonResponse
    {
        return $this->respond(fn () => $this->time->addManual($batchStep, $request->validated(), $request->user()), 201);
    }

    /** DELETE /batch-step-time-entries/{timeEntry} — supervisor removes a wrong entry. */
    public function destroy(Request $request, BatchStepTimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('update', $timeEntry->batchStep->batch->workOrder);

        $this->time->delete($timeEntry);

        return response()->json(['message' => __('Time entry deleted.')]);
    }

    /**
     * GET /work-orders/{workOrder}/operation-times — every step of every batch with
     * its standard, confirmed and recorded labor / machine times, for costing.
     */
    public function workOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        $workOrder->load(['batches.steps.workstation:id,code,name']);
        $rows = $workOrder->batches->sortBy('batch_number')
            ->flatMap(fn ($batch) => $batch->steps->sortBy('step_number')->map(fn (BatchStep $s) => StepTimeService::operation($s, $batch)))
            ->values();

        return response()->json([
            'data' => $rows,
            'totals' => [
                'labor_minutes' => (int) $rows->sum('actual_labor_minutes'),
                'machine_minutes' => (int) $rows->sum('actual_machine_minutes'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public static function entry(BatchStepTimeEntry $e): array
    {
        return [
            'id' => $e->id,
            'batch_step_id' => $e->batch_step_id,
            'rework_operation_id' => $e->rework_operation_id,
            'kind' => $e->kind,
            'source' => $e->source,
            'user' => $e->user ? ['id' => $e->user->id, 'name' => $e->user->name] : null,
            'workstation' => $e->workstation ? ['id' => $e->workstation->id, 'code' => $e->workstation->code, 'name' => $e->workstation->name] : null,
            'started_at' => $e->started_at?->toIso8601String(),
            'ended_at' => $e->ended_at?->toIso8601String(),
            'minutes' => (int) round($e->durationSeconds() / 60),
            'note' => $e->note,
            'created_by_id' => $e->created_by_id,
            'ended_by_id' => $e->ended_by_id,
        ];
    }

    private function respond(callable $fn, int $status = 200): JsonResponse
    {
        try {
            $entry = $fn();
        } catch (\DomainException $e) {
            return $this->error($e);
        }

        return response()->json([
            'data' => self::entry($entry->load(['user:id,name', 'workstation:id,code,name'])),
        ], $entry->wasRecentlyCreated ? $status : 200);
    }

    private function error(\DomainException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'errors' => ['time' => [$e->getMessage()]],
        ], 422);
    }
}
