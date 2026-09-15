<?php

namespace App\Services\Machine;

use App\Models\BatchStep;
use App\Models\MachineCounter;
use App\Models\MachineCounterReading;
use App\Models\MachineEvent;
use App\Models\MachineTag;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\MachineProductionService;
use App\Support\ProductionFlow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MachineCounterService
{
    public function forSource(MachineTag|TopicMapping $source): MachineCounter
    {
        $tag = $source instanceof MachineTag;
        $connection = $tag ? $source->connection : $source->topic?->machineConnection;
        abort_unless($connection, 404);

        return MachineCounter::firstOrCreate(
            [$tag ? 'machine_tag_id' : 'topic_mapping_id' => $source->id],
            ['machine_connection_id' => $connection->id,
                'workstation_id' => $tag ? $source->workstation_id : null,
                'mode' => $tag ? ($source->signal_type === 'cycle_complete' ? 'pulse' : 'cumulative')
                    : ($source->action_type === TopicMapping::ACTION_COUNT_STEP ? 'pulse' : (($source->action_params['qty_increment'] ?? false) ? 'increment' : 'cumulative')),
                'kind' => $tag ? match ($source->signal_type) {
                    'reject_count' => 'reject', 'good_count' => 'good', default => 'total'
                } : 'good'],
        );
    }

    public function configure(MachineCounter $counter, array $data, int $userId): void
    {
        DB::transaction(function () use ($counter, $data, $userId) {
            $counter = MachineCounter::whereKey($counter->id)->lockForUpdate()->firstOrFail();
            $ws = Workstation::findOrFail($data['workstation_id']);
            $connection = $counter->connection;
            if (! $connection || (string) $connection->tenant_id !== (string) $ws->line?->tenant_id
                || ($connection->line_id && $connection->line_id !== $ws->line_id)
                || ($counter->machine_tag_id && $counter->tag?->workstation_id !== $ws->id)) {
                $this->fail('The counter and workstation must belong to the same machine line.');
            }
            if ($counter->topic_mapping_id && strpbrk($counter->mapping?->topic?->topic_pattern ?? '', '+#') !== false) {
                $this->fail('Use an exact MQTT topic for a production counter; wildcard topics can mix machines.');
            }
            $step = empty($data['batch_step_id']) ? null : BatchStep::findOrFail($data['batch_step_id']);
            if ($step) {
                $this->validateTarget($counter, $step, $ws->id);
                // Serialize competing assignments using the same order -> step lock order as ingestion.
                WorkOrder::whereKey($step->batch->work_order_id)->lockForUpdate()->firstOrFail();
                BatchStep::whereKey($step->id)->lockForUpdate()->firstOrFail();
                if ($data['kind'] === 'good' && MachineCounter::where('batch_step_id', $step->id)->where('kind', 'good')->whereKeyNot($counter->id)->exists()) {
                    $this->fail('This step already has a good-count channel. Unassign it before connecting another source.');
                }
            }
            $before = $counter->only(['mode', 'kind', 'workstation_id', 'batch_step_id']);
            $counter->fill([
                'mode' => $data['mode'], 'kind' => $data['kind'], 'workstation_id' => $ws->id,
                'batch_step_id' => $step?->id,
            ]);
            if (! $counter->isDirty() && $counter->configured_at && $counter->source_fingerprint === $this->fingerprint($counter)) {
                return;
            }
            $counter->fill(['last_raw' => null, 'last_read_at' => null, 'configured_at' => now(),
                'reset_required' => false, 'source_fingerprint' => $this->fingerprint($counter)])->save();
            $counter->readings()->create(['status' => 'configured', 'observed_at' => now(), 'batch_step_id' => $step?->id,
                'payload' => ['before' => $before, 'after' => $counter->only(array_keys($before))],
                'reviewed_by_id' => $userId, 'reviewed_at' => now(), 'review_note' => $data['note']]);
        });
    }

    public function ingest(MachineCounter $counter, mixed $value, ?Carbon $at = null, ?string $eventId = null, ?array $sourceSnapshot = null): MachineCounterReading
    {
        $hasTimestamp = $at !== null;
        $at = ($at ?? now())->copy()->utc();

        return DB::transaction(function () use ($counter, $value, $at, $eventId, $hasTimestamp, $sourceSnapshot) {
            // One lock serializes baseline, assignment, deduplication and production.
            // All subsequent production locks follow order -> step, as BatchService does.
            $counter = MachineCounter::whereKey($counter->id)->lockForUpdate()->firstOrFail();
            if ($eventId !== null && $eventId !== '' && ($duplicate = $counter->readings()->where('event_id', $eventId)->first())) {
                return $duplicate;
            }
            $numeric = is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 && (float) $value <= 999999999999.99
                && abs((float) $value - round((float) $value, 2)) < 0.000001;
            $raw = $numeric ? round((float) $value, 2) : null;
            $status = null;
            $delta = 0.0;
            $applied = 0.0;
            if (! $numeric) {
                $status = 'invalid_value';
            } elseif (! $counter->configured_at) {
                $status = 'unconfigured';
            } elseif ($sourceSnapshot !== null && ! $this->matchesSnapshot($counter, $sourceSnapshot)) {
                $status = 'source_changed';
                $counter->reset_required = true;
            } elseif (! $this->sourceActive($counter) || $counter->source_fingerprint !== $this->fingerprint($counter)) {
                $status = 'source_changed';
                $counter->reset_required = true;
            } elseif ($at->greaterThan(now()->addSeconds(5))) {
                $status = 'future_reading';
            } elseif ($at->lessThan($counter->configured_at) || ($counter->last_read_at && ($at->lessThan($counter->last_read_at) || ($counter->mode === 'cumulative' && $at->equalTo($counter->last_read_at))))) {
                $status = 'out_of_order';
            } elseif ($counter->reset_required) {
                $status = 'reset_required';
            } elseif (! $hasTimestamp) {
                $status = 'timestamp_required';
            } elseif ($counter->mode !== 'cumulative' && (! $eventId || strlen($eventId) > 160)) {
                $status = 'event_id_required';
            } elseif ($counter->mode === 'pulse' && $raw !== 1.0) {
                $status = 'invalid_pulse';
            } else {
                if ($counter->mode === 'cumulative') {
                    if ($counter->last_raw === null) {
                        $status = 'baseline';
                    } elseif ($raw < (float) $counter->last_raw) {
                        $status = 'reset_required';
                        $counter->reset_required = true;
                    } else {
                        $delta = round($raw - (float) $counter->last_raw, 2);
                    }
                } else {
                    $delta = $raw;
                }
                if (! $counter->reset_required) {
                    $counter->last_raw = $raw;
                    $counter->last_read_at = $at;
                }
                if ($status === null) {
                    [$applied, $status] = $this->apply($counter, $counter->step, $delta);
                }
            }
            $counter->save();
            $reading = $counter->readings()->create([
                'batch_step_id' => $counter->batch_step_id, 'event_id' => $eventId !== '' && strlen($eventId ?? '') <= 160 ? $eventId : null,
                'payload' => ['value' => $value, 'kind' => $counter->kind, 'mode' => $counter->mode, 'workstation_id' => $counter->workstation_id],
                'raw_value' => $raw, 'delta' => $delta, 'applied_qty' => $applied, 'status' => $status, 'observed_at' => $at,
            ]);
            if ($delta > 0) {
                MachineEvent::create(['workstation_id' => $counter->workstation_id, 'machine_connection_id' => $counter->machine_connection_id,
                    'event_type' => MachineEvent::TYPE_COUNTER, 'event_timestamp' => $at, 'correlation_id' => (string) Str::uuid(),
                    'payload' => ['kind' => $counter->kind, 'value' => $raw, 'delta' => $delta, 'applied_qty' => $applied, 'counter_reading_id' => $reading->id]]);
            }

            return $reading;
        }, 3);
    }

    public function rebaseline(MachineCounter $counter, string $note, int $userId): void
    {
        DB::transaction(function () use ($counter, $note, $userId) {
            $counter = MachineCounter::whereKey($counter->id)->lockForUpdate()->firstOrFail();
            if ($counter->mode !== 'cumulative') {
                $this->fail('Only cumulative counters require a baseline.');
            }
            if (! $counter->configured_at || $counter->source_fingerprint !== $this->fingerprint($counter)) {
                $this->fail('Save the counter configuration after changing its source.');
            }
            // The next fresh reading establishes the baseline; never manufacture a delta.
            $counter->update(['last_raw' => null, 'last_read_at' => null, 'configured_at' => now(), 'reset_required' => false,
                'source_fingerprint' => $this->fingerprint($counter)]);
            $counter->readings()->create(['status' => 'rebaseline', 'observed_at' => now(), 'review_note' => $note,
                'reviewed_by_id' => $userId, 'reviewed_at' => now(), 'batch_step_id' => $counter->batch_step_id]);
        });
    }

    public function review(MachineCounter $counter, int $readingId, array $data, int $userId): void
    {
        DB::transaction(function () use ($counter, $readingId, $data, $userId) {
            $counter = MachineCounter::whereKey($counter->id)->lockForUpdate()->firstOrFail();
            $reading = $counter->readings()->whereKey($readingId)->lockForUpdate()->firstOrFail();
            if ($reading->reviewed_at || ! in_array($reading->status, ['unassigned', 'blocked', 'partial', 'quality_unknown'], true)) {
                $this->fail('This reading cannot be reconciled again.');
            }
            $applied = 0.0;
            if ($data['decision'] === 'apply') {
                if (($reading->payload['kind'] ?? null) !== 'good') {
                    $this->fail('Only confirmed good counts can be applied to production.');
                }
                $step = BatchStep::findOrFail($data['batch_step_id']);
                $this->validateTarget($counter, $step, $counter->workstation_id);
                $remaining = round((float) $reading->delta - (float) $reading->applied_qty, 2);
                [$applied] = $this->apply($counter, $step, $remaining);
                if ($applied !== $remaining || $applied <= 0) {
                    $this->fail('The selected step cannot accept the full remaining quantity.');
                }
            }
            $reading->update(['reviewed_by_id' => $userId, 'reviewed_at' => now(), 'review_note' => $data['note']]);
            // Preserve the original attribution and append the reconciliation separately.
            $counter->readings()->create(['status' => $data['decision'] === 'apply' ? 'reconciled' : 'dismissed',
                'observed_at' => now(), 'batch_step_id' => isset($step) ? $step->id : null, 'applied_qty' => $applied,
                'payload' => ['reading_id' => $reading->id], 'reviewed_by_id' => $userId, 'reviewed_at' => now(), 'review_note' => $data['note']]);
        });
    }

    private function apply(MachineCounter $counter, ?BatchStep $step, float $delta): array
    {
        if ($delta <= 0) {
            return [0.0, 'unchanged'];
        }
        if (! $step) {
            return [0.0, 'unassigned'];
        }
        if ($counter->kind !== 'good') {
            return [0.0, 'quality_unknown'];
        }
        $order = $step->batch?->workOrder;
        if (! $order) {
            return [0.0, 'blocked'];
        }
        $order = WorkOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
        $step = BatchStep::whereKey($step->id)->lockForUpdate()->firstOrFail();
        if (! $order->isMachineCounted() || $step->workstation_id !== $counter->workstation_id
            || (string) $order->tenant_id !== (string) $counter->connection?->tenant_id
            || $order->line_id !== $counter->workstation?->line_id
            || ($counter->connection?->line_id && $order->line_id !== $counter->connection->line_id)
            || in_array($order->status, WorkOrder::TERMINAL_STATUSES, true)
            || $step->status !== BatchStep::STATUS_IN_PROGRESS || $step->productionBlocker()) {
            return [0.0, 'blocked'];
        }
        $applied = app(BatchService::class)->recordMachinePass($step, min($delta, $step->availableQty()));
        // Whole-batch mode keeps its legacy order total, only at the final step.
        if (! ProductionFlow::isTransfer() && $step->batch->lastEffectiveStep()?->is($step) && $applied > 0) {
            app(MachineProductionService::class)->recordGoodCount($order, $applied);
        }

        return [$applied, $applied <= 0 ? 'blocked' : ($applied < $delta ? 'partial' : 'applied')];
    }

    private function validateTarget(MachineCounter $counter, BatchStep $step, int $workstationId): void
    {
        $order = $step->batch?->workOrder;
        if (! $order || ! $order->isMachineCounted() || $step->workstation_id !== $workstationId
            || (string) $order->tenant_id !== (string) $counter->connection?->tenant_id
            || $order->line_id !== Workstation::find($workstationId)?->line_id
            || in_array($step->status, [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED], true)
            || in_array($order->status, WorkOrder::TERMINAL_STATUSES, true)) {
            $this->fail('Choose an open machine-counted step at this workstation.');
        }
    }

    private function sourceActive(MachineCounter $counter): bool
    {
        if (! $counter->connection?->is_active) {
            return false;
        }

        return $counter->machine_tag_id ? (bool) $counter->tag?->is_active
            : (bool) ($counter->mapping?->is_active && $counter->mapping?->topic?->is_active);
    }

    /** Snapshot the settings actually used to decode the reading, not a refreshed database model. */
    public function sourceSnapshot(MachineTag|TopicMapping $source): array
    {
        return ['attributes' => $source->only($source instanceof MachineTag
            ? ['address', 'transform', 'signal_type', 'workstation_id', 'data_type', 'register_type', 'unit']
            : ['field_path', 'action_type', 'action_params', 'condition_expr'])];
    }

    private function matchesSnapshot(MachineCounter $counter, array $snapshot): bool
    {
        $source = $counter->machine_tag_id ? $counter->tag : $counter->mapping;
        if (! $source || $source->only(array_keys($snapshot['attributes'])) != $snapshot['attributes']) {
            return false;
        }

        return ! isset($snapshot['modbus']) || $counter->connection?->modbusConnection?->only(array_keys($snapshot['modbus'])) == $snapshot['modbus'];
    }

    private function fingerprint(MachineCounter $counter): string
    {
        $source = $counter->machine_tag_id ? $counter->tag : $counter->mapping;

        $connection = $counter->connection;
        $transport = match ($connection?->protocol) {
            'modbus' => $connection->modbusConnection?->only(['host', 'port', 'unit_id', 'byte_order', 'word_order']),
            'opcua' => $connection->opcuaConnection?->only(['endpoint_url']),
            'mqtt' => $connection->mqttConnection?->only(['broker_host', 'broker_port']),
            default => null,
        };

        return hash('sha256', json_encode([
            'source' => $source?->only($counter->machine_tag_id
                ? ['address', 'transform', 'signal_type', 'workstation_id', 'data_type', 'register_type', 'unit']
                : ['field_path', 'action_type', 'action_params', 'condition_expr']),
            'topic' => $counter->mapping?->topic?->topic_pattern,
            'connection' => $connection?->only(['protocol', 'line_id']),
            'transport' => $transport,
        ]));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['counter' => __($message)]);
    }
}
