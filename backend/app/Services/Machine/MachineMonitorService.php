<?php

namespace App\Services\Machine;

use App\Models\MachineEvent;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Support\Collection;

/**
 * Read model for the live machine monitor. Derives current state, today's
 * availability and production counts directly from the workstation_states
 * timeline and the machine_events store.
 */
class MachineMonitorService
{
    /**
     * Live status for one workstation: current state, time in state, today's
     * availability % and good/reject counts.
     */
    public function liveStatus(Workstation $workstation): array
    {
        $dayStart = now()->startOfDay();

        $current = WorkstationState::where('workstation_id', $workstation->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        $slices = WorkstationState::where('workstation_id', $workstation->id)
            ->where('started_at', '>=', $dayStart)
            ->get();

        $counters = MachineEvent::where('workstation_id', $workstation->id)
            ->where('event_type', MachineEvent::TYPE_COUNTER)
            ->where('event_timestamp', '>=', $dayStart)
            ->get();

        return $this->computeStatus($workstation, $current, $slices, $counters);
    }

    /**
     * Compute a workstation's live status from already-fetched state slices and
     * counter events. Shared by liveStatus() (single) and fleetStatus() (batched)
     * so the aggregation logic lives in one place.
     */
    private function computeStatus(
        Workstation $workstation,
        ?WorkstationState $current,
        Collection $slices,
        Collection $counters,
    ): array {
        // Sum durations per state today (closed slices + the open one up to now).
        $runningSec = 0;
        $lossSec = 0;
        $totalSec = 0;
        foreach ($slices as $s) {
            $end = $s->ended_at ?? now();
            $dur = max(0, (int) $s->started_at->diffInSeconds($end));
            $totalSec += $dur;
            if ($s->state === WorkstationState::RUNNING) {
                $runningSec += $dur;
            } elseif (in_array($s->state, WorkstationState::LOSS_STATES, true)) {
                $lossSec += $dur;
            }
        }

        $availability = $totalSec > 0 ? round($runningSec / $totalSec * 100, 1) : null;

        $good = 0;
        $reject = 0;
        foreach ($counters as $c) {
            $delta = (float) ($c->payload['delta'] ?? 0);
            if (($c->payload['kind'] ?? 'good') === 'reject') {
                $reject += $delta;
            } else {
                $good += $delta;
            }
        }

        $total = $good + $reject;
        $quality = $total > 0 ? round($good / $total * 100, 1) : null;

        return [
            'workstation' => $workstation,
            'state' => $current?->state ?? 'UNKNOWN',
            'since' => $current?->started_at,
            'metadata' => $current?->metadata ?? [],
            'availability' => $availability,
            'quality' => $quality,
            'good' => $good,
            'reject' => $reject,
            'running_seconds' => $runningSec,
            'loss_seconds' => $lossSec,
            'is_live' => $current !== null,
        ];
    }

    /**
     * Fleet view: live status for every workstation that has machine tags
     * (i.e. is wired to a machine connection).
     */
    public function fleetStatus(): array
    {
        $workstationIds = \App\Models\MachineTag::query()
            ->whereNotNull('workstation_id')
            ->distinct()
            ->pluck('workstation_id');

        if ($workstationIds->isEmpty()) {
            return [];
        }

        $workstations = Workstation::with('line:id,name')
            ->whereIn('id', $workstationIds)
            ->orderBy('name')
            ->get();

        $dayStart = now()->startOfDay();

        // Three batched queries for the whole fleet (instead of 3 per workstation),
        // grouped by workstation_id for O(1) lookups in computeStatus().
        $openStates = WorkstationState::whereIn('workstation_id', $workstationIds)
            ->whereNull('ended_at')
            ->get()
            ->groupBy('workstation_id');

        $slices = WorkstationState::whereIn('workstation_id', $workstationIds)
            ->where('started_at', '>=', $dayStart)
            ->get()
            ->groupBy('workstation_id');

        $counters = MachineEvent::whereIn('workstation_id', $workstationIds)
            ->where('event_type', MachineEvent::TYPE_COUNTER)
            ->where('event_timestamp', '>=', $dayStart)
            ->get()
            ->groupBy('workstation_id');

        return $workstations->map(function (Workstation $w) use ($openStates, $slices, $counters) {
            // Mirror the single-row query's "latest open state" semantics.
            $current = ($openStates->get($w->id) ?? collect())
                ->sortByDesc('started_at')
                ->first();

            return $this->computeStatus(
                $w,
                $current,
                $slices->get($w->id) ?? collect(),
                $counters->get($w->id) ?? collect(),
            );
        })->all();
    }

    public function stateColor(string $state): string
    {
        return match ($state) {
            WorkstationState::RUNNING => 'green',
            WorkstationState::IDLE => 'amber',
            WorkstationState::SETUP => 'blue',
            WorkstationState::STOPPED => 'gray',
            WorkstationState::FAULT => 'red',
            WorkstationState::WAITING => 'yellow',
            WorkstationState::CLEANING => 'purple',
            WorkstationState::MAINTENANCE => 'orange',
            default => 'slate',
        };
    }
}
