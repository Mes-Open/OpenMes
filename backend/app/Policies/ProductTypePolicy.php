<?php

namespace App\Policies;

use App\Models\ProductType;
use App\Models\User;

class ProductTypePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, ProductType $pt): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, ProductType $pt): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, ProductType $pt): bool { return $user->hasRole('Admin'); }
}
