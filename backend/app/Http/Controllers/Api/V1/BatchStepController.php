<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatchStep;
use App\Services\WorkOrder\BatchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BatchStepController extends Controller
{
    public function __construct(
        protected BatchService $batchService
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
     * Report a problem on a step.
     *
     * @param Request $request
     * @param BatchStep $batchStep
     * @return JsonResponse
     */
    public function problem(Request $request, BatchStep $batchStep): JsonResponse
    {
        $this->authorize('view', $batchStep->batch->workOrder);

        // This will be implemented in Phase 4: Issue/Andon
        return response()->json([
            'message' => 'Issue reporting will be implemented in Phase 4',
        ], 501);
    }
}
