<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;

class DivisionPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Division $d): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, Division $d): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, Division $d): bool { return $user->hasRole('Admin'); }
}
