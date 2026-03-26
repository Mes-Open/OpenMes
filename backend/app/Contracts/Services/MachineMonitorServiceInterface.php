<?php

namespace App\Contracts\Services;

use App\Models\Workstation;
use App\Contracts\Industrial\MachineInterface;

interface MachineMonitorServiceInterface
{
    public function updateWorkstationState(Workstation $workstation, MachineInterface $machine): void;
}
