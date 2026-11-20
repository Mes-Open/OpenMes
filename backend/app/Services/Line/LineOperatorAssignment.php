<?php

namespace App\Services\Line;

use App\Events\User\UserAssignedToLine;
use App\Models\Line;
use App\Models\LineUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Lines → Operators: putting an operator account on a production line
 * and taking it off again. One place for the rules, used by the line page and
 * by the Production Planner's "Suggest substitute" action, so both behave the
 * same — only Operator accounts, no double assignment, the module hook fires.
 *
 * The line is touched on every change by the {@see LineUser} pivot, whatever
 * the path, so screens that follow lines live (the planner's staffing badges
 * and insights) refresh.
 */
class LineOperatorAssignment
{
    /**
     * @throws \DomainException with a user-facing message when the account cannot be assigned
     */
    public function assign(Line $line, User $user): void
    {
        if (! $user->hasRole('Operator')) {
            throw new \DomainException(__('Only operators can be assigned to production lines.'));
        }
        if ($line->users()->where('user_id', $user->id)->exists()) {
            throw new \DomainException(__('Operator is already assigned to this line.'));
        }

        $line->users()->attach($user->id);

        // Module hook: a user was assigned to a production line — only once
        // the assignment is committed, never for one a caller rolls back.
        DB::afterCommit(fn () => UserAssignedToLine::dispatch($user, $line));
    }

    public function unassign(Line $line, User $user): void
    {
        $line->users()->detach($user->id);
    }
}
