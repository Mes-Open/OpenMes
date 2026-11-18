<?php

namespace App\Policies;

use App\Models\PlanningInsightAcknowledgement;
use App\Models\User;

/**
 * Production Planner insights are a planning tool: Admins and Supervisors see
 * them and act on them. An Operator granted the Schedule tab still sees the
 * board, never the insights or the staffing behind them.
 */
class PlanningInsightAcknowledgementPolicy
{
    private const PLANNERS = ['Admin', 'Supervisor'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::PLANNERS);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::PLANNERS);
    }

    public function delete(User $user, PlanningInsightAcknowledgement $acknowledgement): bool
    {
        return $user->hasAnyRole(self::PLANNERS);
    }
}
