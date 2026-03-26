<?php

namespace App\Listeners;

use App\Events\Issue\IssueCreated;
use App\Events\Issue\IssueResolved;
use App\Contracts\Services\WorkOrderServiceInterface;
use App\Models\Issue;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

class UpdateWorkOrderStatusOnIssueChange
{
    public function __construct(
        protected WorkOrderServiceInterface $workOrderService
    ) {}

    /**
     * Handle IssueCreated event.
     */
    public function handleIssueCreated(IssueCreated $event): void
    {
        $issue = $event->issue;
        if ($issue->issueType->is_blocking) {
            $this->blockWorkOrder($issue->work_order_id);
        }
    }

    /**
     * Handle IssueResolved event.
     */
    public function handleIssueResolved(IssueResolved $event): void
    {
        $issue = $event->issue;
        if ($issue->issueType->is_blocking) {
            $this->checkAndUnblockWorkOrder($issue->work_order_id);
        }
    }

    protected function blockWorkOrder(int $workOrderId): void
    {
        $workOrder = WorkOrder::find($workOrderId);
        if ($workOrder && !in_array($workOrder->status, [WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_DONE, WorkOrder::STATUS_CANCELLED])) {
            $workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);
            Log::info("Work order {$workOrderId} blocked via event listener.");
        }
    }

    protected function checkAndUnblockWorkOrder(int $workOrderId): void
    {
        $workOrder = WorkOrder::find($workOrderId);
        if ($workOrder && $workOrder->status === WorkOrder::STATUS_BLOCKED) {
            $hasBlockingIssues = Issue::where('work_order_id', $workOrderId)
                ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                ->whereHas('issueType', fn($q) => $q->where('is_blocking', true))
                ->exists();

            if (!$hasBlockingIssues) {
                $this->workOrderService->updateWorkOrderStatus($workOrder);
                Log::info("Work order {$workOrderId} unblocked via event listener.");
            }
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            IssueCreated::class => 'handleIssueCreated',
            IssueResolved::class => 'handleIssueResolved',
        ];
    }
}
