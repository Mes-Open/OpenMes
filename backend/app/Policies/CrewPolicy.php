<?php

namespace App\Policies;

use App\Models\Crew;
use App\Models\User;

class CrewPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Crew $crew): bool { return true; }
    public function create(User $user): bool { return $user->hasRole('Admin'); }
    public function update(User $user, Crew $crew): bool { return $user->hasRole('Admin'); }
    public function delete(User $user, Crew $crew): bool { return $user->hasRole('Admin'); }
}
