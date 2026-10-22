<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;
use App\Support\InspectionPermissions;

/**
 * Who may read, carry out and decide on inbound inspections.
 *
 * The one rule set behind both the API (Api\V1\InspectionController) and the
 * operator screen (Web\Operator\InspectionController) — neither checks a role
 * of its own. It reads permissions only, never role names: Supervisor and Admin
 * pass because the seeder grants them both permissions, and an operator passes
 * once somebody grants them one.
 */
class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canPerform($user);
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return $this->canPerform($user);
    }

    public function create(User $user): bool
    {
        return $this->canPerform($user);
    }

    public function recordResult(User $user, Inspection $inspection): bool
    {
        return $this->canPerform($user);
    }

    public function complete(User $user, Inspection $inspection): bool
    {
        return $this->canPerform($user);
    }

    /**
     * Release, quarantine, scrap … the inspected lot. Performing the
     * inspection is not enough: this is the decision that stocks material in.
     */
    public function disposition(User $user, Inspection $inspection): bool
    {
        return $user->checkPermissionTo(InspectionPermissions::DISPOSITION);
    }

    /** Disposition is the higher right and includes performing. */
    private function canPerform(User $user): bool
    {
        return $user->checkPermissionTo(InspectionPermissions::PERFORM)
            || $user->checkPermissionTo(InspectionPermissions::DISPOSITION);
    }
}
