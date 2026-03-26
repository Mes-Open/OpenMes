<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatchStep;
use App\Contracts\Services\BatchServiceInterface;
use App\Contracts\Services\IssueServiceInterface;
use App\Http\Resources\BatchStepResource;
use App\Http\Resources\IssueResource;
use App\Traits\StandardApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BatchStepController extends Controller
{
    use StandardApiResponse;

    public function __construct(
        protected BatchServiceInterface $batchService,
        protected IssueServiceInterface $issueService
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

            return $this->success(
                new BatchStepResource($step->load(['startedBy', 'batch.workOrder'])),
                'Step started successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422, ['step' => [$e->getMessage()]]);
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

            return $this->success(
                new BatchStepResource($step->load(['completedBy', 'batch.workOrder'])),
                'Step completed successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422, ['step' => [$e->getMessage()]]);
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

            return $this->success(
                [
                    'issue' => new IssueResource($issue),
                    'work_order_blocked' => $issue->issueType->is_blocking,
                ],
                'Issue reported successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->error('Failed to report issue', 422, ['issue' => [$e->getMessage()]]);
        }
    }
}
