<?php

namespace App\Services\EventStore;

use App\Models\MachineEvent;
use App\Models\Workstation;
use Illuminate\Support\Str;

class MachineEventStore
{
    /**
     * Record a machine event in the persistent event store.
     */
    public function record(Workstation $workstation, string $type, array $payload, ?string $correlationId = null): MachineEvent
    {
        $lastEvent = MachineEvent::where('workstation_id', $workstation->id)
            ->latest('event_timestamp')
            ->first();

        $event = MachineEvent::create([
            'workstation_id' => $workstation->id,
            'event_type' => $type,
            'state_from' => $lastEvent?->state_to ?? $workstation->state,
            'state_to' => $payload['state'] ?? $workstation->state,
            'payload' => $payload,
            'event_timestamp' => now()->format('Y-m-d H:i:s.u'),
            'correlation_id' => $correlationId ?? Str::uuid(),
            'synced_to_cloud' => false,
        ]);

        // Trigger asynchronous projections (OEE calculation, dashboard updates)
        $this->dispatchProjections($event);

        return $event;
    }

    /**
     * Replay events to rebuild workstation state for any point in time.
     */
    public function replay(Workstation $workstation, $atTimestamp): string
    {
        $event = MachineEvent::where('workstation_id', $workstation->id)
            ->where('event_timestamp', '<=', $atTimestamp)
            ->whereNotNull('state_to')
            ->latest('event_timestamp')
            ->first();

        return $event?->state_to ?? 'IDLE';
    }

    protected function dispatchProjections(MachineEvent $event): void
    {
        // Emit industrial domain event for real-time projections (OEE, dashboards, alerts)
        event(new \App\Events\Industrial\MachineEventRecorded($event));
    }
}
