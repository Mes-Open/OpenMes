<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'workstation_id',
        'event_type',
        'state_from',
        'state_to',
        'payload',
        'event_timestamp',
        'synced_to_cloud',
        'correlation_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'event_timestamp' => 'datetime',
        'synced_to_cloud' => 'boolean',
    ];

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }
}
