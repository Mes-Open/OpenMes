<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WageGroup;

class WageGroupPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, WageGroup $wg): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, WageGroup $wg): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, WageGroup $wg): bool { return $user->hasRole('Admin'); }
}
