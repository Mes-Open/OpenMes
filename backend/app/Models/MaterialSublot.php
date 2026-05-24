<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Logical subdivision of a {@see MaterialLot}.
 *
 * Tenancy is inherited transitively via the parent lot; we don't carry tenant_id
 * directly here to keep the schema lean and avoid the risk of a sublot being
 * orphaned in a different tenant from its lot.
 */
class MaterialSublot extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CONSUMED = 'consumed';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_RESERVED,
        self::STATUS_CONSUMED,
    ];

    protected $fillable = [
        'parent_lot_id',
        'sublot_number',
        'quantity',
        'unit_of_measure',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function parentLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'parent_lot_id');
    }
}
