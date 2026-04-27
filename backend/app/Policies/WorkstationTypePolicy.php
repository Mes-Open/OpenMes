<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkstationType;

class WorkstationTypePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, WorkstationType $wt): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, WorkstationType $wt): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, WorkstationType $wt): bool { return $user->hasRole('Admin'); }
}
