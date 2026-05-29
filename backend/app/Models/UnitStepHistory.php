<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single processing event for a serialised unit at a workstation — the
 * "birth certificate" line for full per-unit genealogy.
 */
class UnitStepHistory extends Model
{
    use HasFactory;

    protected $table = 'unit_step_history';

    protected $fillable = [
        'serial_unit_id',
        'batch_step_id',
        'workstation_id',
        'operator_id',
        'parameters',
        'result',
        'notes',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class);
    }

    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
