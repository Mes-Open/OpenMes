<?php

namespace App\Listeners;

use App\Events\Machine\WorkstationStateChanged;
use App\Services\Production\StepTimeService;

/**
 * Machine time from connectivity (#400): a workstation's RUNNING state opens
 * and closes machine time on the step in progress there. See
 * StepTimeService::onWorkstationStateChanged() for the rules.
 */
class RecordStepMachineTime
{
    public function __construct(private StepTimeService $time) {}

    public function handle(WorkstationStateChanged $event): void
    {
        $this->time->onWorkstationStateChanged($event->workstation, $event->from, $event->to, $event->state->started_at);
    }
}
