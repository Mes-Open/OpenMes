<?php

namespace App\Services\Industrial;

use App\Contracts\Services\MachineMonitorServiceInterface;
use App\Contracts\Industrial\MachineInterface;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Support\Facades\Log;

class MachineMonitorService implements MachineMonitorServiceInterface
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
            $oldStateName = 'IDLE';
            if ($currentState) {
                $oldStateName = $currentState->state;
                $now = now();
                $currentState->update([
                    'ended_at' => $now,
                    'duration_seconds' => $now->diffInSeconds($currentState->started_at),
                ]);
            }

            $newStateRecord = WorkstationState::create([
                'workstation_id' => $workstation->id,
                'state' => $machineState,
                'started_at' => now(),
                'metadata' => [
                    'telemetry' => $machine->getTelemetry(),
                ],
            ]);

            event(new \App\Events\Industrial\MachineStateChanged(
                $workstation,
                $oldStateName,
                $machineState,
                $newStateRecord
            ));

            // If state is STOPPED or FAULTED, trigger a potential downtime event
            if (in_array($machineState, ['STOPPED', 'FAULTED'])) {
                \App\Models\DowntimeEvent::create([
                    'workstation_id' => $workstation->id,
                    'started_at' => now(),
                    'downtime_category' => $machineState === 'FAULTED' ? 'Unplanned' : 'Planned',
                    'description' => "Automated downtime record from machine telemetry ({$machineState})",
                    'metadata' => ['telemetry' => $machine->getTelemetry()],
                ]);
            }

            // If we transitioned AWAY from STOPPED/FAULTED, close recent open downtime events
            if (!in_array($machineState, ['STOPPED', 'FAULTED'])) {
                $openDowntime = \App\Models\DowntimeEvent::where('workstation_id', $workstation->id)
                    ->whereNull('ended_at')
                    ->latest()
                    ->first();

                if ($openDowntime) {
                    $now = now();
                    $openDowntime->update([
                        'ended_at' => $now,
                        'duration_minutes' => (int) ceil($now->diffInMinutes($openDowntime->started_at)),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Machine monitor error for workstation {$workstation->code}: {$e->getMessage()}");
        }
    }
}
