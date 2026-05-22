<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialLot extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_QUARANTINED = 'quarantined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DEPLETED = 'depleted';

    protected $fillable = [
        'material_id',
        'lot_number',
        'supplier_id',
        'supplier_lot_ref',
        'received_qty',
        'available_qty',
        'manufacture_date',
        'expiry_date',
        'received_at',
        'inspection_id',
        'status',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'received_qty' => 'decimal:4',
            'available_qty' => 'decimal:4',
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function picks(): HasMany
    {
        return $this->hasMany(AllocationLotPick::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)->where('available_qty', '>', 0);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function markDepletedIfEmpty(): void
    {
        if ((float) $this->available_qty <= 0 && $this->status === self::STATUS_AVAILABLE) {
            $this->update(['status' => self::STATUS_DEPLETED]);
        }
    }
}
