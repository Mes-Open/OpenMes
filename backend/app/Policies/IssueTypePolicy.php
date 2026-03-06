<?php

namespace App\Policies;

use App\Models\IssueType;
use App\Models\User;

class IssueTypePolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, IssueType $issueType): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, IssueType $issueType): bool
    {
        return $user->hasRole('Admin');
    }
}
