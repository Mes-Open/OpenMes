<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowerLotAllocation extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'flower_lot_id',
        'batch_id',
        'weight_kg',
        'seep_count',
        'allocated_by_id',
        'allocated_at',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:3',
            'allocated_at' => 'datetime',
        ];
    }

    public function flowerLot(): BelongsTo
    {
        return $this->belongsTo(FlowerLot::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_id');
    }
}
