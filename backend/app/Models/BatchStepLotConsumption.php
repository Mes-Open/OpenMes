<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Junction recording a single lot/sublot consumption event by a batch step.
 *
 * The combination of (batch_step_id, material_lot_id, consumed_at) is the
 * unit of genealogy — many rows per step are allowed because the same lot
 * may be drawn multiple times for the same step.
 */
class BatchStepLotConsumption extends Model
{
    use HasFactory;

    protected $table = 'batch_step_lot_consumption';

    protected $fillable = [
        'batch_step_id',
        'material_lot_id',
        'sublot_id',
        'quantity_consumed',
        'consumed_at',
        'recorded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'quantity_consumed' => 'decimal:4',
        ];
    }

    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }

    public function sublot(): BelongsTo
    {
        return $this->belongsTo(MaterialSublot::class, 'sublot_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
