<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DowntimeEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'workstation_id',
        'anomaly_reason_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'downtime_category',
        'description',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'metadata' => 'array',
    ];

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function anomalyReason(): BelongsTo
    {
        return $this->belongsTo(AnomalyReason::class);
    }
}
