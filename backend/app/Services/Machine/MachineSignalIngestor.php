<?php

namespace App\Services\Machine;

use App\Models\MachineEvent;
use App\Models\MachineTag;
use App\Models\Workstation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The single, protocol-agnostic entry point for machine data. Every adapter
 * (Modbus poller, MQTT listener, OPC UA gateway) normalizes a reading into a
 * (tag, raw value) pair and calls ingest(). This routes the signal by type:
 *
 *   state          → workstation state machine (+ auto downtime)
 *   good/reject    → counter deltas → produced/scrap + per-workstation OEE
 *   cycle_complete → unit pulse
 *   telemetry      → state metadata snapshot
 *   alarm          → fault / event log
 *
 * All signals are also appended to the MachineEvent store for replay/audit.
 */
class MachineSignalIngestor
{
    public function __construct(private readonly WorkstationStateMachine $stateMachine) {}

    public function ingest(MachineTag $tag, mixed $rawValue, ?Carbon $at = null): void
    {
        $at ??= now();
        $value = $tag->applyTransform($rawValue);
        $workstation = $tag->workstation;

        match ($tag->signal_type) {
            MachineTag::SIGNAL_STATE => $this->handleState($tag, $workstation, (string) $value, $at),
            MachineTag::SIGNAL_GOOD_COUNT => $this->handleCounter($tag, $workstation, $value, 'good', $at),
            MachineTag::SIGNAL_REJECT_COUNT => $this->handleCounter($tag, $workstation, $value, 'reject', $at),
            MachineTag::SIGNAL_CYCLE_COMPLETE => $this->handleCounter($tag, $workstation, $value, 'good', $at),
            MachineTag::SIGNAL_TELEMETRY => $this->handleTelemetry($tag, $workstation, $value, $at),
            MachineTag::SIGNAL_ALARM => $this->handleAlarm($tag, $workstation, $value, $at),
            default => null,
        };
    }

    private function handleState(MachineTag $tag, ?Workstation $ws, string $state, Carbon $at): void
    {
        if (! $ws) {
            return;
        }

        $current = $this->stateMachine->current($ws);

        // Only record an event on an actual transition — polling re-reads the
        // same state every cycle and we don't want a flood of no-op events.
        if ($current?->state === $state) {
            return;
        }

        $this->stateMachine->transition($ws, $state, [], $at);

        $this->record($ws, $tag, MachineEvent::TYPE_STATE_CHANGE, $at, [
            'from' => $current?->state,
            'to' => $state,
        ], $current?->state, $state);
    }

    /**
     * Counters are cumulative on the machine; we store the last reading per tag
     * and emit the delta. Counter resets (new < last) are treated as the new
     * value to avoid negative spikes.
     */
    private function handleCounter(MachineTag $tag, ?Workstation $ws, mixed $value, string $kind, Carbon $at): void
    {
        if (! $ws || ! is_numeric($value)) {
            return;
        }

        $value = (float) $value;
        $cacheKey = "machine_tag_last:{$tag->id}";
        $last = Cache::get($cacheKey);
        Cache::put($cacheKey, $value, now()->addDay());

        $delta = ($last === null || $value < $last) ? ($last === null ? 0 : $value) : ($value - $last);
        if ($delta <= 0) {
            return;
        }

        $this->record($ws, $tag, MachineEvent::TYPE_COUNTER, $at, [
            'kind' => $kind,
            'value' => $value,
            'delta' => $delta,
        ]);
    }

    private function handleTelemetry(MachineTag $tag, ?Workstation $ws, mixed $value, Carbon $at): void
    {
        if (is_float($value)) {
            $value = round($value, 2);
        }

        if ($ws) {
            $current = $this->stateMachine->current($ws);
            if ($current) {
                $current->update(['metadata' => array_merge($current->metadata ?? [], [$tag->name => $value])]);
            }
        }

        $this->record($ws, $tag, MachineEvent::TYPE_TELEMETRY, $at, [$tag->name => $value]);
    }

    private function handleAlarm(MachineTag $tag, ?Workstation $ws, mixed $value, Carbon $at): void
    {
        // Truthy alarm value → record an alarm event. Issue creation is left to
        // a downstream listener so policy stays configurable.
        if (! $value) {
            return;
        }

        $this->record($ws, $tag, MachineEvent::TYPE_ALARM, $at, [
            'tag' => $tag->name,
            'value' => $value,
        ]);
    }

    private function record(?Workstation $ws, MachineTag $tag, string $type, Carbon $at, array $payload, ?string $from = null, ?string $to = null): void
    {
        MachineEvent::create([
            'workstation_id' => $ws?->id,
            'machine_connection_id' => $tag->machine_connection_id,
            'event_type' => $type,
            'state_from' => $from,
            'state_to' => $to,
            'payload' => $payload,
            'event_timestamp' => $at->format('Y-m-d H:i:s.u'),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
