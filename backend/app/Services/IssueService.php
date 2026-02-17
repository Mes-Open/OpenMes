<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\WorkOrder;
use App\Models\BatchStep;
use App\Models\IssueType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IssueService
{
    /**
     * Create a new issue and optionally block the work order.
     */
    public function createIssue(array $data): Issue
    {
        return DB::transaction(function () use ($data) {
            // Set reported_at timestamp
            $data['reported_at'] = now();
            $data['status'] = Issue::STATUS_OPEN;

            // Create the issue
            $issue = Issue::create($data);

            // Load the issue type to check if it's blocking
            $issue->load('issueType');

            // If this is a blocking issue, block the work order
            if ($issue->issueType->is_blocking) {
                $this->blockWorkOrder($issue->work_order_id);
            }

            Log::info('Issue created', [
                'issue_id' => $issue->id,
                'work_order_id' => $issue->work_order_id,
                'is_blocking' => $issue->issueType->is_blocking,
            ]);

            return $issue->load(['issueType', 'reportedBy', 'workOrder', 'batchStep']);
        });
    }

    /**
     * Acknowledge an issue.
     */
    public function acknowledgeIssue(Issue $issue, int $userId): Issue
    {
        if (!in_array($issue->status, [Issue::STATUS_OPEN])) {
            throw new \InvalidArgumentException('Only OPEN issues can be acknowledged');
        }

        return DB::transaction(function () use ($issue, $userId) {
            $issue->update([
                'status' => Issue::STATUS_ACKNOWLEDGED,
                'acknowledged_at' => now(),
                'assigned_to_id' => $userId,
            ]);

            Log::info('Issue acknowledged', [
                'issue_id' => $issue->id,
                'acknowledged_by' => $userId,
            ]);

            return $issue->fresh(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep']);
        });
    }

    /**
     * Resolve an issue (mark as fixed but not yet verified).
     */
    public function resolveIssue(Issue $issue, string $resolutionNotes = null): Issue
    {
        if (!in_array($issue->status, [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])) {
            throw new \InvalidArgumentException('Only OPEN or ACKNOWLEDGED issues can be resolved');
        }

        return DB::transaction(function () use ($issue, $resolutionNotes) {
            $issue->update([
                'status' => Issue::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolution_notes' => $resolutionNotes,
            ]);

            // If this was a blocking issue, check if we should unblock the work order
            if ($issue->issueType->is_blocking) {
                $this->checkAndUnblockWorkOrder($issue->work_order_id);
            }

            Log::info('Issue resolved', [
                'issue_id' => $issue->id,
                'work_order_id' => $issue->work_order_id,
            ]);

            return $issue->fresh(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep']);
        });
    }

    /**
     * Close an issue (final state).
     */
    public function closeIssue(Issue $issue): Issue
    {
        if ($issue->status !== Issue::STATUS_RESOLVED) {
            throw new \InvalidArgumentException('Only RESOLVED issues can be closed');
        }

        return DB::transaction(function () use ($issue) {
            $issue->update([
                'status' => Issue::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            Log::info('Issue closed', [
                'issue_id' => $issue->id,
            ]);

            return $issue->fresh(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep']);
        });
    }

    /**
     * Block a work order.
     */
    protected function blockWorkOrder(int $workOrderId): void
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        // Only block if not already blocked or done
        if (!in_array($workOrder->status, [WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_DONE, WorkOrder::STATUS_CANCELLED])) {
            $workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

            Log::info('Work order blocked due to issue', [
                'work_order_id' => $workOrderId,
            ]);
        }
    }

    /**
     * Check if work order should be unblocked (no more blocking issues).
     */
    protected function checkAndUnblockWorkOrder(int $workOrderId): void
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        // Only process if currently blocked
        if ($workOrder->status !== WorkOrder::STATUS_BLOCKED) {
            return;
        }

        // Check if there are any remaining blocking issues
        $hasBlockingIssues = Issue::where('work_order_id', $workOrderId)
            ->blocking()
            ->exists();

        if (!$hasBlockingIssues) {
            // Determine the appropriate status to restore
            // If there are in-progress batches, set to IN_PROGRESS, otherwise PENDING
            $hasBatchesInProgress = $workOrder->batches()
                ->where('status', 'IN_PROGRESS')
                ->exists();

            $newStatus = $hasBatchesInProgress
                ? WorkOrder::STATUS_IN_PROGRESS
                : WorkOrder::STATUS_PENDING;

            $workOrder->update(['status' => $newStatus]);

            Log::info('Work order unblocked', [
                'work_order_id' => $workOrderId,
                'new_status' => $newStatus,
            ]);
        }
    }

    /**
     * Get all issues for a work order.
     */
    public function getWorkOrderIssues(int $workOrderId, ?string $status = null)
    {
        $query = Issue::where('work_order_id', $workOrderId)
            ->with(['issueType', 'reportedBy', 'assignedTo', 'batchStep'])
            ->orderBy('reported_at', 'desc');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get all issues for a line.
     */
    public function getLineIssues(int $lineId, ?string $status = null)
    {
        $query = Issue::whereHas('workOrder', function ($q) use ($lineId) {
            $q->where('line_id', $lineId);
        })
            ->with(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep'])
            ->orderBy('reported_at', 'desc');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get statistics for a line's issues.
     */
    public function getLineIssueStats(int $lineId): array
    {
        $issues = Issue::whereHas('workOrder', function ($q) use ($lineId) {
            $q->where('line_id', $lineId);
        })->get();

        return [
            'total' => $issues->count(),
            'open' => $issues->where('status', Issue::STATUS_OPEN)->count(),
            'acknowledged' => $issues->where('status', Issue::STATUS_ACKNOWLEDGED)->count(),
            'resolved' => $issues->where('status', Issue::STATUS_RESOLVED)->count(),
            'closed' => $issues->where('status', Issue::STATUS_CLOSED)->count(),
            'blocking' => $issues->filter(fn($i) => $i->isBlocking())->count(),
        ];
    }
}
