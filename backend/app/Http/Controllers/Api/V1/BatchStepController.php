<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatchStep;
use App\Services\WorkOrder\BatchService;
use App\Services\IssueService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BatchStepController extends Controller
{
    public function __construct(
        protected BatchService $batchService,
        protected IssueService $issueService
    ) {}

    /**
     * Start a batch step.
     *
     * @param Request $request
     * @param BatchStep $batchStep
     * @return JsonResponse
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
     *
     * @param Request $request
     * @param BatchStep $batchStep
     * @return JsonResponse
     */
    public function complete(Request $request, BatchStep $batchStep): JsonResponse
    {
        $this->authorize('view', $batchStep->batch->workOrder);

        $validated = $request->validate([
            'produced_qty' => 'nullable|numeric|min:0',
        ]);

        try {
            $step = $this->batchService->completeStep(
                $batchStep,
                $request->user(),
                $validated
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
     * Report a problem on a step (creates an issue).
     *
     * @param Request $request
     * @param BatchStep $batchStep
     * @return JsonResponse
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
