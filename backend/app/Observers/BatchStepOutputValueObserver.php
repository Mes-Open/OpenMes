<?php

namespace App\Observers;

use App\Models\BatchStepOutputValue;
use App\Models\Issue;
use App\Models\IssueType;
use App\Services\IssueService;
use App\Services\WorkOrder\OutputGateEvaluator;
use Illuminate\Support\Facades\Log;

/**
 * A recorded output that fails its configured expected result
 * (TemplateStepOutput expected_min/expected_max/expected_value) auto-raises a
 * blocking Issue on the work order — reusing WorkOrder::isBlocked() /
 * BatchStep::canStart() (BatchStep.php:311-322) rather than inventing new
 * blocking logic. An open blocking Issue on a work order already stops every
 * step's canStart() on that order, so this is the whole enforcement mechanism.
 * Best-effort like the sibling BatchStepEventObserver: a throwing evaluator or
 * issue-service call must never break the operator's save.
 */
class BatchStepOutputValueObserver
{
    public function __construct(
        private readonly OutputGateEvaluator $evaluator,
        private readonly IssueService $issues,
    ) {}

    public function created(BatchStepOutputValue $value): void
    {
        try {
            $value->loadMissing(['output', 'batchStep.batch']);

            if ($this->evaluator->passes($value)) {
                return;
            }

            $issueType = IssueType::where('code', 'IN_PROCESS_QC_FAIL')->first();

            if (! $issueType) {
                Log::warning('Quality gate failed but IN_PROCESS_QC_FAIL issue type is missing — no issue raised', [
                    'batch_step_output_value_id' => $value->id,
                ]);

                return;
            }

            $this->issues->createIssue([
                'work_order_id' => $value->batchStep->batch->work_order_id,
                'batch_step_id' => $value->batch_step_id,
                'issue_type_id' => $issueType->id,
                'source' => Issue::SOURCE_IN_PROCESS,
                'title' => __('Quality gate failed: :label', ['label' => $value->output->label]),
                'description' => __('Recorded value did not meet the expected result configured for this step.'),
                'reported_by_id' => $value->recorded_by_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BatchStepOutputValue quality-gate hook failed', ['error' => $e->getMessage()]);
        }
    }
}
