<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasRole('Admin') || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, User $target): bool
    {
        // Cannot delete yourself.
        if ($user->id === $target->id) {
            return false;
        }
        return $user->hasRole('Admin');
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->hasRole('Admin');
    }

    public function manageLines(User $user, User $target): bool
    {
        return $user->hasRole('Admin');
    }
}
