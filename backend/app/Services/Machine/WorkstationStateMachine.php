<?php

namespace App\Services\Machine;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\MachineConnection;
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
 *
 * The open state row's `updated_at` is "last heard from": every signal ingested
 * for the station bumps it (markSignal(), at most once a minute), so a collector
 * that keeps re-reading the same state is told apart from one that went silent.
 * The plant overview reads it to show a line as "No data".
 */
class WorkstationStateMachine
{
    /** markSignal() writes at most once per this many seconds per station. */
    public const SIGNAL_TOUCH_SECONDS = 60;

    /**
     * When this instance last issued markSignal()'s UPDATE, per station — so a
     * long-lived poller sends the statement once a minute, not once a pulse.
     *
     * @var array<int, int> workstation id → unix timestamp
     */
    private array $signalTouchedAt = [];

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
                    // `updated_at` is "last heard from the machine" (see
                    // markSignal()); a note added by hand is not, so a manual
                    // write leaves it alone.
                    $current->metadata = array_merge($current->metadata ?? [], $metadata);
                    $current->timestamps = $source !== 'manual';
                    $current->save();
                    $current->timestamps = true;
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

    /**
     * Record that the station was heard from: bump the open state row's
     * `updated_at`, throttled to once per SIGNAL_TOUCH_SECONDS. A query-builder
     * update on purpose — no read, and no model `updated` event, so a healthy
     * poller doesn't nudge the shift monitor every minute for nothing. Uses
     * now(), not the observed time: a gateway flushing a buffer is alive now.
     *
     * Only collectors call this (MachineSignalIngestor), never transition():
     * an operator re-setting the same state is not a sign of life from the
     * machine and must not make a silent line look live.
     */
    public function markSignal(Workstation $workstation): void
    {
        $now = now()->getTimestamp();
        $last = $this->signalTouchedAt[$workstation->id] ?? null;
        if ($last !== null && $now - $last < self::SIGNAL_TOUCH_SECONDS) {
            return;
        }
        $this->signalTouchedAt[$workstation->id] = $now;

        WorkstationState::where('workstation_id', $workstation->id)
            ->whereNull('ended_at')
            ->where('updated_at', '<', now()->subSeconds(self::SIGNAL_TOUCH_SECONDS))
            ->update(['updated_at' => now()]);
    }

    /**
     * markSignal() for every station a connection reads, when the collector
     * proves it is alive without posting a value — an OPC UA gateway only
     * posts on change, so a station stuck in FAULT goes quiet while its
     * gateway keeps heart-beating. One statement, throttled per row like
     * markSignal() and equally free of model events.
     */
    public function markConnectionSignal(MachineConnection $connection): void
    {
        $stationIds = $connection->activeTags()->whereNotNull('workstation_id')->distinct()->pluck('workstation_id');
        if ($stationIds->isEmpty()) {
            return;
        }

        WorkstationState::whereIn('workstation_id', $stationIds)
            ->whereNull('ended_at')
            ->where('source', 'machine')
            ->where('updated_at', '<', now()->subSeconds(self::SIGNAL_TOUCH_SECONDS))
            ->update(['updated_at' => now()]);
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
