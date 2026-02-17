<?php

namespace App\Events\User;

use App\Models\User;
use App\Models\Line;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAssignedToLine
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Line $line
    ) {}
}
