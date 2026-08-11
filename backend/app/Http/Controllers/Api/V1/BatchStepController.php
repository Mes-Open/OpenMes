<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignBatchStepWorkstationRequest;
use App\Http\Requests\Api\V1\CompleteBatchStepRequest;
use App\Models\BatchStep;
use App\Services\IssueService;
use App\Services\WorkOrder\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchStepController extends Controller
{
    public function __construct(
        protected BatchService $batchService,
        protected IssueService $issueService
    ) {}

    /**
     * Start a batch step.
     */
    public function start(Request $request, BatchStep $batchStep): JsonResponse
    {
        $this->authorize('view', $batchStep->batch->workOrder);

        try {
            $step = $this->batchService->startStep($batchStep, $request->user());

            return response()->json([
                'message' => 'Step started successfully',
                'data' => $step->load(['startedBy', 'batch.workOrder']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'step' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Complete a batch step.
     */
    public function complete(CompleteBatchStepRequest $request, BatchStep $batchStep): JsonResponse
    {
        try {
            $step = $this->batchService->completeStep(
                $batchStep,
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'message' => 'Step completed successfully',
                'data' => $step->load(['completedBy', 'batch.workOrder']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'step' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Pool dispatch (#52): a supervisor assigns a specific workstation to a pending
     * step that carries only an Equipment Class. Route-gated to Supervisor|Admin.
     */
    public function assign(AssignBatchStepWorkstationRequest $request, BatchStep $batchStep): JsonResponse
    {
        try {
            $step = $this->batchService->assignWorkstation($batchStep, (int) $request->validated('workstation_id'), $request->user());

            return response()->json([
                'message' => 'Workstation assigned successfully',
                'data' => $step->load(['workstation', 'workstationType', 'assignedBy']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['step' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Acknowledge that the operator has read this step's critical instructions,
     * clearing the read-confirmation completion gate enforced in
     * BatchService::completeStep. Only meaningful for steps flagged
     * requires_confirmation. Records who confirmed and when. Idempotent.
     */
    public function confirmInstructions(Request $request, BatchStep $batchStep): JsonResponse
    {
        $this->authorize('view', $batchStep->batch->workOrder);

        if (! $batchStep->requires_confirmation) {
            return response()->json([
                'message' => 'This step does not require read-confirmation.',
                'errors' => [
                    'step' => ['This step does not require read-confirmation.'],
                ],
            ], 422);
        }

        $batchStep->markReadConfirmed($request->user());

        return response()->json([
            'message' => 'Instructions acknowledged',
            'data' => $batchStep->load(['confirmedBy', 'batch.workOrder']),
        ]);
    }

    /**
     * Report a problem on a step (creates an issue).
     */
    public function problem(Request $request, BatchStep $batchStep): JsonResponse
    {
        $this->authorize('view', $batchStep->batch->workOrder);

        $validated = $request->validate([
            'issue_type_id' => 'required|integer|exists:issue_types,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
        ]);

        try {
            $batch = $batchStep->batch;
            $workOrder = $batch->workOrder;

            $issue = $this->issueService->createIssue([
                'work_order_id' => $workOrder->id,
                'batch_step_id' => $batchStep->id,
                'issue_type_id' => $validated['issue_type_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'reported_by_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Issue reported successfully',
                'data' => [
                    'issue' => $issue,
                    'work_order_blocked' => $issue->issueType->is_blocking,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to report issue',
                'errors' => [
                    'issue' => [$e->getMessage()],
                ],
            ], 422);
        }
    }
}
