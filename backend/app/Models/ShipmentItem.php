<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One unit a shipment is expected to carry — a pallet or a loose carton, named
 * by its label number. `loaded_at` is set when the dock scans it onto the truck
 * and cleared again by an undo; the scan log keeps both events.
 */
class ShipmentItem extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    public const TYPE_PALLET = 'pallet';

    public const TYPE_CARTON = 'carton';

    public const TYPES = [self::TYPE_PALLET, self::TYPE_CARTON];

    protected $fillable = [
        'shipment_id',
        'unit_type',
        'identifier',
        'pallet_id',
        'carton_id',
        'loaded_at',
        'loaded_by_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'loaded_at' => 'datetime',
        ];
    }

    public function isLoaded(): bool
    {
        return $this->loaded_at !== null;
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function carton(): BelongsTo
    {
        return $this->belongsTo(UnitCarton::class, 'carton_id');
    }

    public function loadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by_id');
    }
}
