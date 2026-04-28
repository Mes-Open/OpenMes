<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workstation;

class WorkstationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workstation $workstation): bool
    {
        if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
            return true;
        }
        return $user->lines()->where('lines.id', $workstation->line_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Workstation $workstation): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Workstation $workstation): bool
    {
        return $user->hasRole('Admin');
    }
}
