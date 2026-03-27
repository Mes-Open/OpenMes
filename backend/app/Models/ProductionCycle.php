<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'workstation_id',
        'work_order_id',
        'batch_id',
        'worker_id',
        'tool_id',
        'started_at',
        'ended_at',
        'cycle_time_seconds',
        'ideal_cycle_time_seconds',
        'is_micro_stop',
        'telemetry',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'cycle_time_seconds' => 'float',
        'ideal_cycle_time_seconds' => 'float',
        'is_micro_stop' => 'boolean',
        'telemetry' => 'array',
    ];

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
