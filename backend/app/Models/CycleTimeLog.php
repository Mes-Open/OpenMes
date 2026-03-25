<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleTimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'workstation_id',
        'batch_id',
        'cycle_time_secs',
        'ideal_time_secs',
        'variability_pct',
        'completed_at',
        'context',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'context' => 'array',
        'cycle_time_secs' => 'float',
        'ideal_time_secs' => 'float',
        'variability_pct' => 'float',
    ];

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
