<?php

namespace App\Services\Quality;

use App\Models\Issue;
use App\Models\IssueAction;
use App\Models\User;

/**
 * Lifecycle of corrective / preventive actions attached to an Issue:
 * OPEN → IN_PROGRESS → DONE → VERIFIED. Closing the parent issue is gated on all
 * actions being VERIFIED (see IssueService::closeIssue).
 */
class IssueActionService
{
    /**
     * @param  array{type:string,title:string,description?:?string,assigned_to_id?:?int,due_date?:?string}  $data
     */
    public function create(Issue $issue, array $data): IssueAction
    {
        return $issue->actions()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => IssueAction::STATUS_OPEN,
        ]);
    }

    public function start(IssueAction $action): IssueAction
    {
        if ($action->status !== IssueAction::STATUS_OPEN) {
            throw new \DomainException("Only an OPEN action can be started (is {$action->status}).");
        }

        $action->update(['status' => IssueAction::STATUS_IN_PROGRESS]);

        return $action->fresh();
    }

    public function complete(IssueAction $action, User $by, ?string $notes = null): IssueAction
    {
        if (! in_array($action->status, [IssueAction::STATUS_OPEN, IssueAction::STATUS_IN_PROGRESS], true)) {
            throw new \DomainException("Only an open/in-progress action can be completed (is {$action->status}).");
        }

        $action->update([
            'status' => IssueAction::STATUS_DONE,
            'completed_at' => now(),
            'completed_by_id' => $by->id,
            'notes' => $notes ?? $action->notes,
        ]);

        return $action->fresh();
    }

    public function verify(IssueAction $action, User $by): IssueAction
    {
        if ($action->status !== IssueAction::STATUS_DONE) {
            throw new \DomainException("Only a completed (DONE) action can be verified (is {$action->status}).");
        }

        $action->update([
            'status' => IssueAction::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by_id' => $by->id,
        ]);

        return $action->fresh();
    }
}
