<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;
    use HasTenant;

    public const TYPE_ALLOCATION = 'allocation';
    public const TYPE_CONSUME = 'consume';
    public const TYPE_RETURN = 'return';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_SCRAP = 'scrap';
    public const TYPE_TRANSFER = 'transfer';

    public const SOURCE_BATCH = 'batch';
    public const SOURCE_BATCH_STEP = 'batch_step';
    public const SOURCE_INSPECTION = 'inspection';
    public const SOURCE_MANUAL_ADJUST = 'manual_adjust';
    public const SOURCE_RECEIPT = 'receipt';

    protected $fillable = [
        'material_id',
        'movement_type',
        'quantity',
        'balance_after',
        'source_type',
        'source_id',
        'reason',
        'performed_by',
        'performed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'performed_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForMaterial($query, int $materialId)
    {
        return $query->where('material_id', $materialId)->orderByDesc('performed_at');
    }
}
