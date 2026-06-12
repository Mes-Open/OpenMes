<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protocol-agnostic tag → signal mapping. Adapters resolve a raw value from the
 * tag's address, apply the transform, and emit a normalized signal of the given
 * signal_type into MachineSignalIngestor.
 */
class MachineTag extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    public const SIGNAL_STATE = 'state';

    public const SIGNAL_GOOD_COUNT = 'good_count';

    public const SIGNAL_REJECT_COUNT = 'reject_count';

    public const SIGNAL_CYCLE_COMPLETE = 'cycle_complete';

    public const SIGNAL_TELEMETRY = 'telemetry';

    public const SIGNAL_ALARM = 'alarm';

    protected $fillable = [
        'machine_connection_id',
        'workstation_id',
        'name',
        'address',
        'signal_type',
        'data_type',
        'register_type',
        'transform',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'transform' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class, 'machine_connection_id');
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /**
     * Apply scale/offset/value-map to a raw reading, returning the semantic value.
     */
    public function applyTransform(mixed $raw): mixed
    {
        $t = $this->transform ?? [];

        // Discrete value map (e.g. {1: RUNNING, 2: IDLE}) — used for state signals.
        if (! empty($t['value_map']) && is_array($t['value_map'])) {
            $key = is_bool($raw) ? ($raw ? '1' : '0') : (string) $raw;

            return $t['value_map'][$key] ?? ($t['value_map']['default'] ?? $raw);
        }

        // Numeric scale/offset for analog telemetry / counters.
        if (is_numeric($raw)) {
            $value = (float) $raw;
            if (isset($t['scale'])) {
                $value *= (float) $t['scale'];
            }
            if (isset($t['offset'])) {
                $value += (float) $t['offset'];
            }

            return $value;
        }

        return $raw;
    }
}
