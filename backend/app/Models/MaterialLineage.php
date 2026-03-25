<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialLineage extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_lot_no',
        'final_unit_no',
        'workstation_id',
        'user_id',
        'batch_id',
        'batch_step_id',
        'process_id',
        'parameters',
        'processed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'processed_at' => 'datetime',
    ];

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class, 'batch_step_id');
    }
}
