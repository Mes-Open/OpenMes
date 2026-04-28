<?php

namespace App\Policies;

use App\Models\Factory;
use App\Models\User;

class FactoryPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Factory $f): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, Factory $f): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, Factory $f): bool { return $user->hasRole('Admin'); }
}
