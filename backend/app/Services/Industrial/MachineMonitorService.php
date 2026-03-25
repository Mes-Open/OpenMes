<?php

namespace App\Services\Industrial;

use App\Contracts\Industrial\MachineInterface;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Support\Facades\Log;

class MachineMonitorService
{
    /**
     * Update the workstation state based on machine telemetry.
     */
    public function updateWorkstationState(Workstation $workstation, MachineInterface $machine): void
    {
        try {
            $machineState = $machine->getCurrentState();
            $currentState = $workstation->currentState();

            // If state hasn't changed, just update telemetry metadata
            if ($currentState && $currentState->state === $machineState) {
                $currentState->update([
                    'metadata' => array_merge($currentState->metadata ?? [], [
                        'last_check' => now(),
                        'telemetry' => $machine->getTelemetry(),
                    ])
                ]);
                return;
            }

            // State changed - end the current state and start a new one
            if ($currentState) {
                $now = now();
                $currentState->update([
                    'ended_at' => $now,
                    'duration_seconds' => $now->diffInSeconds($currentState->started_at),
                ]);
            }

            WorkstationState::create([
                'workstation_id' => $workstation->id,
                'state' => $machineState,
                'started_at' => now(),
                'metadata' => [
                    'telemetry' => $machine->getTelemetry(),
                ],
            ]);

            // If state is STOPPED or FAULTED, trigger a potential downtime event
            if (in_array($machineState, ['STOPPED', 'FAULTED'])) {
                // Logic to start a DowntimeEvent (Phase 2)
            }

        } catch (\Exception $e) {
            Log::error("Machine monitor error for workstation {$workstation->code}: {$e->getMessage()}");
        }
    }
}
