<?php

namespace App\Events\Industrial;

use App\Models\MachineEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MachineEventRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public MachineEvent $event) {}
}
