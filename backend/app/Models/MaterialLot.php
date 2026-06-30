<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
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
    use SoftDeletesWithAudit;

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
        'source_container_no',
        'inspection_id',
        'issue_id',
        'source_batch_id',
        'created_by_id',
        'tenant_id',
        'extra_data',
        'hold_reason',
        'held_at',
        'held_by_id',
        'released_at',
        'released_by_id',
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
            'held_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function isOnHold(): bool
    {
        return $this->status === self::STATUS_QUARANTINE;
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

    /** The non-conformance (issue) this lot is held against, if any. */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    /**
     * The batch that produced this lot (for semi-finished / multi-stage lots).
     * Null for inbound raw lots received from a supplier.
     */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'source_batch_id');
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

    /**
     * Picks made by the allocation engine. One row per (allocation, lot) pair
     * with picked_qty + strategy used. Complements ISA-95 BatchStepLotConsumption
     * at the planning/allocation layer.
     */
    public function picks(): HasMany
    {
        return $this->hasMany(AllocationLotPick::class);
    }

    // ── State checks ────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_RELEASED && (float) $this->quantity_available > 0;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /**
     * Flip a released lot to 'consumed' once quantity reaches zero. Status
     * transitions for received → released and quarantine → rejected are
     * owned by DispositionService.
     */
    public function markConsumedIfEmpty(): void
    {
        if ((float) $this->quantity_available <= 0 && $this->status === self::STATUS_RELEASED) {
            $this->update([
                'quantity_available' => 0,
                'status' => self::STATUS_CONSUMED,
            ]);
        }
    }

    // ── Mutations ───────────────────────────────────────────────────────────

    /**
     * Consume a quantity from this lot, transitioning to 'consumed' when depleted.
     *
     * @throws \InvalidArgumentException when $quantity <= 0
     * @throws \DomainException when $quantity exceeds available
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

    /**
     * Released lots that still have quantity left — eligible for picking.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RELEASED)
            ->where('quantity_available', '>', 0);
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\MaterialSublot::class, 'parent_lot_id'],
        ];
    }
}
