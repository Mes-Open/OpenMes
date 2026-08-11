<?php

namespace App\Services\Machine;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\ProductionDowntime;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Support\ShiftWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns the per-workstation state timeline and the automatic downtime it drives.
 *
 * Entering a downtime state (STOPPED/FAULT/WAITING → unplanned, CLEANING/
 * MAINTENANCE → planned) opens a ProductionDowntime; leaving it closes the open
 * one. Reasons are auto-provisioned per state so OEE availability reflects real
 * machine behaviour without manual reporting.
 */
class WorkstationStateMachine
{
    /**
     * Transition a workstation to a new state. No-op (metadata refresh only) if
     * the state is unchanged. Returns the new (or current) WorkstationState row.
     *
     * @param  string  $source  'machine' (from a connectivity signal) or 'manual'
     *                          (set by an operator/supervisor in the UI).
     */
    public function transition(Workstation $workstation, string $newState, array $metadata = [], ?Carbon $at = null, string $source = 'machine'): WorkstationState
    {
        if (! in_array($newState, WorkstationState::STATES, true)) {
            throw new \InvalidArgumentException("Unknown workstation state: {$newState}");
        }

        $at ??= now();

        return DB::transaction(function () use ($workstation, $newState, $metadata, $at, $source) {
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
                'source' => $source,
                'metadata' => $metadata ?: null,
            ]);

            if (in_array($newState, WorkstationState::DOWNTIME_STATES, true)) {
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

        // An unplanned stop the machine reported is a fact without an
        // explanation: the AUTO-* reason keeps OEE arithmetic honest, but the
        // row is flagged so the shift monitor asks a human what happened.
        // Planned states (cleaning, maintenance) are self-explanatory.
        $unplanned = in_array($state, WorkstationState::LOSS_STATES, true);

        ProductionDowntime::create([
            'line_id' => $workstation->line_id,
            'workstation_id' => $workstation->id,
            'downtime_reason_id' => $reason->id,
            'needs_reason' => $unplanned,
            'shift_id' => $this->currentShiftId($workstation, $at),
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
     * The shift this stop falls in, so OEE and the shift monitor can scope it.
     * Null when the line runs no configured shift at that moment.
     */
    private function currentShiftId(Workstation $workstation, Carbon $at): ?int
    {
        return ShiftWindow::at($workstation->line_id, $at)?->shift?->id;
    }

    /**
     * Per-state auto downtime reason. STOPPED/FAULT/WAITING are unplanned loss;
     * CLEANING/MAINTENANCE are planned (scheduled) downtime, so OEE treats them
     * as schedule loss, not availability loss (#87). Reasons are provisioned once.
     */
    private function autoReasonFor(string $state): DowntimeReason
    {
        $map = [
            WorkstationState::FAULT => ['code' => 'AUTO-FAULT', 'name' => 'Machine fault (auto)', 'kind' => DowntimeKind::Unplanned->value],
            WorkstationState::STOPPED => ['code' => 'AUTO-STOP', 'name' => 'Machine stopped (auto)', 'kind' => DowntimeKind::Unplanned->value],
            WorkstationState::WAITING => ['code' => 'AUTO-WAIT', 'name' => 'Machine waiting (auto)', 'kind' => DowntimeKind::Unplanned->value],
            WorkstationState::CLEANING => ['code' => 'AUTO-CLEAN', 'name' => 'Machine cleaning (auto)', 'kind' => DowntimeKind::Planned->value],
            WorkstationState::MAINTENANCE => ['code' => 'AUTO-MAINT', 'name' => 'Machine maintenance (auto)', 'kind' => DowntimeKind::Planned->value],
        ];
        $cfg = $map[$state] ?? $map[WorkstationState::STOPPED];

        return DowntimeReason::firstOrCreate(
            ['code' => $cfg['code']],
            ['name' => $cfg['name'], 'kind' => $cfg['kind'], 'is_active' => true]
        );
    }
}
