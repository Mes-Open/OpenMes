<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, Company $c): bool { return true; }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, Company $c): bool { return $u->hasRole('Admin'); }
    public function delete(User $u, Company $c): bool { return $u->hasRole('Admin'); }
}
