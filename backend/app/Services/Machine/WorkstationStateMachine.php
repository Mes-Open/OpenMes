<?php

namespace App\Services\Machine;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\ProductionDowntime;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns the per-workstation state timeline and the automatic downtime it drives.
 *
 * Entering a loss state (STOPPED/FAULT) opens a ProductionDowntime; leaving it
 * closes the open one. Reasons are auto-provisioned per state so OEE
 * availability reflects real machine behaviour without manual reporting.
 */
class WorkstationStateMachine
{
    /**
     * Transition a workstation to a new state. No-op (metadata refresh only) if
     * the state is unchanged. Returns the new (or current) WorkstationState row.
     */
    public function transition(Workstation $workstation, string $newState, array $metadata = [], ?Carbon $at = null): WorkstationState
    {
        if (! in_array($newState, WorkstationState::STATES, true)) {
            throw new \InvalidArgumentException("Unknown workstation state: {$newState}");
        }

        $at ??= now();

        return DB::transaction(function () use ($workstation, $newState, $metadata, $at) {
            $current = $this->current($workstation);

            if ($current && $current->state === $newState) {
                if ($metadata) {
                    $current->update(['metadata' => array_merge($current->metadata ?? [], $metadata)]);
                }

                return $current;
            }

            if ($current) {
                $current->update([
                    'ended_at' => $at,
                    'duration_seconds' => max(0, (int) $current->started_at->diffInSeconds($at)),
                ]);
                $this->closeDowntimeIfOpen($workstation, $at);
            }

            $state = WorkstationState::create([
                'workstation_id' => $workstation->id,
                'state' => $newState,
                'started_at' => $at,
                'source' => 'machine',
                'metadata' => $metadata ?: null,
            ]);

            if (in_array($newState, WorkstationState::LOSS_STATES, true)) {
                $this->openDowntime($workstation, $newState, $at);
            }

            event(new \App\Events\Machine\WorkstationStateChanged(
                $workstation,
                $current?->state,
                $newState,
                $state
            ));

            return $state;
        });
    }

    public function current(Workstation $workstation): ?WorkstationState
    {
        return WorkstationState::where('workstation_id', $workstation->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    private function openDowntime(Workstation $workstation, string $state, Carbon $at): void
    {
        // Avoid duplicate open downtime.
        $alreadyOpen = ProductionDowntime::where('workstation_id', $workstation->id)
            ->whereNull('ended_at')
            ->exists();
        if ($alreadyOpen) {
            return;
        }

        $reason = $this->autoReasonFor($state);

        ProductionDowntime::create([
            'line_id' => $workstation->line_id,
            'workstation_id' => $workstation->id,
            'downtime_reason_id' => $reason->id,
            'started_at' => $at,
            'notes' => __('Auto-recorded from machine state :state', ['state' => $state]),
        ]);
    }

    private function closeDowntimeIfOpen(Workstation $workstation, Carbon $at): void
    {
        $open = ProductionDowntime::where('workstation_id', $workstation->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if ($open) {
            $open->update([
                'ended_at' => $at,
                'duration_minutes' => (int) ceil($open->started_at->diffInSeconds($at) / 60),
            ]);
        }
    }

    /**
     * A FAULT is unplanned loss; a STOPPED machine is treated as unplanned too
     * (operators can re-categorise later). Reasons are provisioned once.
     */
    private function autoReasonFor(string $state): DowntimeReason
    {
        $map = [
            WorkstationState::FAULT => ['code' => 'AUTO-FAULT', 'name' => 'Machine fault (auto)', 'kind' => DowntimeKind::Unplanned->value],
            WorkstationState::STOPPED => ['code' => 'AUTO-STOP', 'name' => 'Machine stopped (auto)', 'kind' => DowntimeKind::Unplanned->value],
        ];
        $cfg = $map[$state] ?? $map[WorkstationState::STOPPED];

        return DowntimeReason::firstOrCreate(
            ['code' => $cfg['code']],
            ['name' => $cfg['name'], 'kind' => $cfg['kind'], 'is_active' => true]
        );
    }
}
