<?php

namespace App\Events\Machine;

use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkstationStateChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Workstation $workstation,
        public ?string $from,
        public string $to,
        public WorkstationState $state,
    ) {}
}
