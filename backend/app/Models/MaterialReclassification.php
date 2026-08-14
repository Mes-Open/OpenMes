<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit record of a material reclassification (#99): either a CLASS
 * move (quantity from one material to another) or a lot STATUS change. Correlates
 * the paired stock_movements (which carry source_id = this id). Ledger-style —
 * not user-deletable, so no soft deletes.
 */
class MaterialReclassification extends Model
{
    use HasFactory;
    use HasTenant;

    public const TYPE_CLASS = 'class';

    public const TYPE_STATUS = 'status';

    protected $fillable = [
        'type',
        'source_material_id',
        'target_material_id',
        'source_lot_id',
        'quantity',
        'from_status',
        'to_status',
        'reason',
        'performed_by',
        'performed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'performed_at' => 'datetime',
        ];
    }

    public function sourceMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'source_material_id');
    }

    public function targetMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'target_material_id');
    }

    public function sourceLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'source_lot_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
