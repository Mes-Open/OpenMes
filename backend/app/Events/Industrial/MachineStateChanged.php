<?php

namespace App\Events\Industrial;

use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MachineStateChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Workstation $workstation,
        public string $oldState,
        public string $newState,
        public ?WorkstationState $stateRecord
    ) {}
}
