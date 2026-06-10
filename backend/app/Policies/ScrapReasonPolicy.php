<?php

namespace App\Policies;

use App\Models\ScrapReason;
use App\Models\User;

class ScrapReasonPolicy
{
    public function viewAny(User $u): bool { return true; }
    public function view(User $u, ScrapReason $r): bool { return true; }
    public function create(User $u): bool { return $u->hasRole('Admin'); }
    public function update(User $u, ScrapReason $r): bool { return $u->hasRole('Admin'); }
    public function delete(User $u, ScrapReason $r): bool { return $u->hasRole('Admin'); }
}
