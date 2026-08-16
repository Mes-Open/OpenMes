<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderChangeRequest;

/**
 * Who may move a production-change request along (#182).
 *
 * The split that matters: raising a change is an ordinary production-management
 * action, but approving and applying one is a control step. Approval and application
 * are therefore gated on the dedicated `approve work order changes` permission rather
 * than on `edit work orders`, so a plant can hand out the two separately — which is
 * the entire reason the change goes through a review instead of a direct edit.
 */
class WorkOrderChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view work orders');
    }

    public function view(User $user, WorkOrderChangeRequest $changeRequest): bool
    {
        return $user->can('view work orders')
            && $user->can('view', $changeRequest->workOrder);
    }

    public function create(User $user): bool
    {
        return $user->can('edit work orders');
    }

    /**
     * A draft belongs to whoever raised it. Supervisors and Admins can also correct
     * one — somebody has to be able to unblock a draft left behind at shift end.
     */
    public function update(User $user, WorkOrderChangeRequest $changeRequest): bool
    {
        if (! $user->can('edit work orders')) {
            return false;
        }

        return $changeRequest->requested_by_id === $user->id
            || $user->hasAnyRole(['Admin', 'Supervisor']);
    }

    /** Submitting for review is part of authoring the request. */
    public function submit(User $user, WorkOrderChangeRequest $changeRequest): bool
    {
        return $this->update($user, $changeRequest);
    }

    public function approve(User $user, WorkOrderChangeRequest $changeRequest): bool
    {
        return $user->can('approve work order changes');
    }

    public function reject(User $user, WorkOrderChangeRequest $changeRequest): bool
    {
        return $this->approve($user, $changeRequest);
    }

    /**
     * Applying is what actually reconfigures the order on the shop floor, so it sits
     * behind the same control as approval.
     */
    public function apply(User $user, WorkOrderChangeRequest $changeRequest): bool
    {
        return $user->can('approve work order changes');
    }

    /** Withdrawing your own request, or a supervisor clearing the board. */
    public function cancel(User $user, WorkOrderChangeRequest $changeRequest): bool
    {
        return $this->update($user, $changeRequest);
    }
}
