<?php

namespace App\Policies;

use App\Models\Line;
use App\Models\User;

class LinePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // role-filtered in controller
    }

    public function view(User $user, Line $line): bool
    {
        if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
            return true;
        }
        return $user->lines()->where('lines.id', $line->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Line $line): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Line $line): bool
    {
        return $user->hasRole('Admin');
    }

    public function manageAssignments(User $user, Line $line): bool
    {
        return $user->hasRole('Admin');
    }
}
