<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PerformQualityControlTaskRequest;
use App\Http\Requests\StoreRoamingQualityControlTaskRequest;
use App\Models\Batch;
use App\Models\Pallet;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Services\Quality\QualityTriggerService;
use Illuminate\Http\JsonResponse;

/**
 * REST twin of the web QualityControlTaskController queue (#105): the
 * outstanding quality-control controls (due + in_progress), skipping one,
 * recording a control (perform), and raising an ad-hoc roaming control.
 * index + skip are behind auth:sanctum (operators and supervisors both work
 * the queue); perform + storeRoaming are role-gated Supervisor|Admin.
 */
class QualityControlTaskController extends Controller
{
    public function __construct(private QualityTriggerService $triggerService) {}

    /** GET /api/v1/quality-control-tasks — outstanding (due + in_progress) controls + perform/roaming metadata. */
    public function index(): JsonResponse
    {
        $tasks = QualityControlTask::whereIn('status', QualityControlTask::OPEN_STATUSES)
            ->with([
                'trigger:id,name,trigger_type,is_blocking,quality_check_template_id',
                'trigger.template:id,parameters,samples_per_check',
                'workOrder:id,order_no',
                'batch:id,batch_number',
                'line:id,name',
                'workstation:id,name',
            ])
            ->orderByDesc('fired_at')
            ->get()
            ->map(fn (QualityControlTask $t) => [
                'id' => $t->id,
                'status' => $t->status,
                'due_reason' => $t->due_reason,
                'fired_at' => $t->fired_at?->toIso8601String(),
                'is_blocking' => (bool) $t->trigger?->is_blocking,
                'quality_control_trigger_id' => $t->quality_control_trigger_id,
                'trigger_name' => $t->trigger?->name,
                'trigger_type' => $t->trigger?->trigger_type,
                'work_order_id' => $t->work_order_id,
                'work_order_no' => $t->workOrder?->order_no,
                'batch_id' => $t->batch_id,
                'batch_number' => $t->batch?->batch_number,
                'line_id' => $t->line_id,
                'line_name' => $t->line?->name,
                'workstation_name' => $t->workstation?->name,
                // Per-task recording metadata so the mobile "perform" form can be
                // built without a separate triggers lookup.
                'parameters' => $t->trigger?->template?->parameters ?? [],
                'samples_per_check' => $t->trigger?->template?->samples_per_check ?? 1,
            ]);

        return response()->json([
            'data' => $tasks,
            'meta' => [
                // Roaming triggers a control can be raised against ad-hoc.
                'roaming_triggers' => QualityControlTrigger::active()
                    ->ofType(QualityControlTrigger::TYPE_ROAMING)
                    ->get(['id', 'name']),
                // In-progress batches a roaming control can target.
                'active_batches' => Batch::where('status', Batch::STATUS_IN_PROGRESS)
                    ->with('workOrder:id,order_no')
                    ->get()
                    ->map(fn (Batch $b) => [
                        'id' => $b->id,
                        'label' => trim(($b->workOrder?->order_no ?? '').' · #'.$b->batch_number, ' ·'),
                    ]),
                // Pallets a recorded control can be linked to (#106) — not yet shipped.
                'pallets' => Pallet::whereIn('status', ['open', 'closed'])
                    ->get(['id', 'pallet_no', 'work_order_id'])
                    ->map(fn (Pallet $p) => [
                        'id' => $p->id,
                        'pallet_no' => $p->pallet_no,
                        'work_order_id' => $p->work_order_id,
                    ]),
            ],
        ]);
    }

    /** POST /api/v1/quality-control-tasks/{task}/skip — skip an outstanding control. */
    public function skip(QualityControlTask $task): JsonResponse
    {
        try {
            $this->triggerService->skipTask($task, request()->user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $task->id, 'status' => $task->fresh()->status]]);
    }

    /** POST /api/v1/quality-control-tasks/{task}/perform — record the control's samples and complete it. */
    public function perform(PerformQualityControlTaskRequest $request, QualityControlTask $task): JsonResponse
    {
        $validated = $request->validated();

        $samples = collect($validated['samples'])->map(fn ($s) => [
            'sample_number' => $s['sample_number'],
            'parameter_name' => $s['parameter_name'],
            'parameter_type' => $s['parameter_type'],
            'value_numeric' => $s['value_numeric'] ?? null,
            'value_boolean' => isset($s['value_boolean']) ? (bool) $s['value_boolean'] : null,
            'is_passed' => isset($s['is_passed']) ? (bool) $s['is_passed'] : null,
        ])->toArray();

        // Optional pallet link (#106): the pallet must belong to the task's work order.
        $pallet = null;
        if (! empty($validated['pallet_id'])) {
            $pallet = Pallet::find($validated['pallet_id']);
            if ($pallet && $task->work_order_id && $pallet->work_order_id !== $task->work_order_id) {
                return response()->json(['message' => __('That pallet belongs to a different work order.')], 422);
            }
        }

        try {
            $task = $this->triggerService->performTask(
                $task,
                $request->user(),
                $samples,
                $validated['production_quantity'] ?? null,
                $validated['notes'] ?? null,
                $pallet,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'id' => $task->id,
            'status' => $task->status,
            'all_passed' => (bool) $task->qualityCheck?->all_passed,
        ]]);
    }

    /** POST /api/v1/quality-control-tasks — raise an ad-hoc roaming control. */
    public function storeRoaming(StoreRoamingQualityControlTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $trigger = QualityControlTrigger::findOrFail($validated['quality_control_trigger_id']);

        if ($trigger->trigger_type !== QualityControlTrigger::TYPE_ROAMING) {
            return response()->json(['message' => __('Only roaming triggers can be raised manually.')], 422);
        }

        try {
            $task = $this->triggerService->createRoamingTask($trigger, [
                'line_id' => $validated['line_id'] ?? null,
                'workstation_id' => $validated['workstation_id'] ?? null,
                'work_order_id' => $validated['work_order_id'] ?? null,
                'batch_id' => $validated['batch_id'] ?? null,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $task->id, 'status' => $task->status]], 201);
    }
}
