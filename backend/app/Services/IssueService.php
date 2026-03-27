<?php

namespace App\Services;

use App\Contracts\Services\IssueServiceInterface;
use App\Models\Issue;
use App\Models\WorkOrder;
use App\Models\BatchStep;
use App\Models\IssueType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IssueService implements IssueServiceInterface
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

            event(new \App\Events\Issue\IssueCreated($issue));

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

            event(new \App\Events\Issue\IssueAcknowledged($issue));

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

            event(new \App\Events\Issue\IssueResolved($issue));

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

            event(new \App\Events\Issue\IssueClosed($issue));

            Log::info('Issue closed', [
                'issue_id' => $issue->id,
            ]);

            return $issue->fresh(['issueType', 'reportedBy', 'assignedTo', 'workOrder', 'batchStep']);
        });
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
