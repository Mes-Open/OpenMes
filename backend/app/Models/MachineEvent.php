<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only machine event (event store). Recorded for every state change,
 * counter pulse and alarm; supports replay and edge→cloud sync.
 */
class MachineEvent extends Model
{
    use HasFactory;

    public const TYPE_STATE_CHANGE = 'state_change';

    public const TYPE_COUNTER = 'counter';

    public const TYPE_ALARM = 'alarm';

    public const TYPE_TELEMETRY = 'telemetry';

    protected $fillable = [
        'workstation_id',
        'machine_connection_id',
        'event_type',
        'state_from',
        'state_to',
        'payload',
        'event_timestamp',
        'correlation_id',
        'synced_to_cloud',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'event_timestamp' => 'datetime',
            'synced_to_cloud' => 'boolean',
        ];
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class, 'machine_connection_id');
    }
}
