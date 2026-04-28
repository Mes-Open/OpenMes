<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, Shift $s): bool { return true; }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, Shift $s): bool { return $u->hasRole('Admin'); }
    public function delete(User $u, Shift $s): bool { return $u->hasRole('Admin'); }
}
