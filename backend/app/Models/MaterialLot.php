<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ISA-95 Material Lot.
 *
 * Represents a physically distinct quantity of a material received in one event.
 * Owns a state machine (received → released/quarantine → consumed/expired/rejected),
 * tracks remaining quantity, and links to the inbound inspection that cleared it.
 */
class MaterialLot extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_QUARANTINE = 'quarantine';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_QUARANTINE,
        self::STATUS_RELEASED,
        self::STATUS_CONSUMED,
        self::STATUS_EXPIRED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'lot_number',
        'material_id',
        'source_id',
        'quantity_received',
        'quantity_available',
        'unit_of_measure',
        'received_at',
        'manufacturing_date',
        'expiry_date',
        'status',
        'supplier_lot_no',
        'supplier_reference',
        'inspection_id',
        'created_by_id',
        'tenant_id',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'quantity_received' => 'decimal:4',
            'quantity_available' => 'decimal:4',
            'extra_data' => 'array',
        ];
    }

    // ── Relations ───────────────────────────────────────────────────────────

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MaterialSource::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function sublots(): HasMany
    {
        return $this->hasMany(MaterialSublot::class, 'parent_lot_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(BatchStepLotConsumption::class, 'material_lot_id');
    }

    // ── State checks ────────────────────────────────────────────────────────

    /**
     * A lot is usable in production only after QC release and while quantity remains.
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_RELEASED && (float) $this->quantity_available > 0;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    // ── Mutations ───────────────────────────────────────────────────────────

    /**
     * Consume a quantity from this lot, transitioning to 'consumed' when depleted.
     *
     * Strict policy: throws on a non-positive amount, throws on overflow.
     * Callers must clamp upstream if a partial consumption is acceptable —
     * silent clamping here would hide bugs in BOM / production logic.
     *
     * @throws \InvalidArgumentException when $quantity <= 0
     * @throws \DomainException          when $quantity exceeds available
     */
    public function consume(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Consume quantity must be positive.');
        }

        $available = (float) $this->quantity_available;
        if ($quantity > $available) {
            throw new \DomainException(sprintf(
                'Insufficient quantity in lot %s (requested %s, available %s).',
                $this->lot_number,
                $quantity,
                $available
            ));
        }

        $remaining = $available - $quantity;
        $this->quantity_available = $remaining;
        if ($remaining <= 0) {
            $this->quantity_available = 0;
            $this->status = self::STATUS_CONSUMED;
        }

        $this->save();
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RELEASED);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RELEASED)
            ->where('quantity_available', '>', 0);
    }
}
