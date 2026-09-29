<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A uniquely identified physical unit. It is known by its serial number (SN)
 * and/or its process serial (PSN): a line may number the unit with a PSN at
 * its first station and give it the SN later, with the product label. Its full
 * process history — every workstation it passed through, with operator and
 * parameter snapshots — lives in UnitStepHistory.
 */
class SerialUnit extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_IN_PRODUCTION = 'in_production';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SCRAPPED = 'scrapped';

    public const STATUS_SHIPPED = 'shipped';

    /** Held after repeated failed tests - not scrap yet, but nothing downstream may take it. */
    public const STATUS_BLOCKED = 'blocked';

    public const STATUSES = [
        self::STATUS_IN_PRODUCTION,
        self::STATUS_COMPLETED,
        self::STATUS_SCRAPPED,
        self::STATUS_SHIPPED,
        self::STATUS_BLOCKED,
    ];

    /** Statuses in which a unit is off the line: no further binding, testing or packing. */
    public const TERMINAL_STATUSES = [self::STATUS_SCRAPPED, self::STATUS_SHIPPED];

    protected $fillable = [
        'serial_no',
        'psn',
        'work_order_id',
        'batch_id',
        'carton_id',
        'pallet_id',
        'material_id',
        'status',
        'produced_at',
        'packed_at',
        'shipped_at',
        'tenant_id',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'produced_at' => 'datetime',
            'packed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'extra_data' => 'array',
        ];
    }

    /** The status as people read it (the same words the screens use). */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PRODUCTION => __('In production'),
            self::STATUS_COMPLETED => __('Unit completed'),
            self::STATUS_SCRAPPED => __('Scrapped'),
            self::STATUS_SHIPPED => __('Shipped'),
            self::STATUS_BLOCKED => __('Blocked'),
            default => (string) $this->status,
        };
    }

    /** How the unit is named on screens and in messages: its SN, else its PSN. */
    public function getDisplayIdAttribute(): string
    {
        return (string) ($this->serial_no ?? $this->psn ?? '#'.$this->id);
    }

    /**
     * The unit a scan names, by SN or PSN (already normalised by the caller).
     * An SN match wins; among PSN matches the newest unit is the one on the bench.
     */
    public static function findByIdentifier(?string $identifier): ?self
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return static::where('serial_no', $identifier)->first()
            ?? static::where('psn', $identifier)->orderByDesc('id')->first();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function carton(): BelongsTo
    {
        return $this->belongsTo(UnitCarton::class, 'carton_id');
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    /** Components bound into this unit, the removed ones included (closed by unbound_at). */
    public function components(): HasMany
    {
        return $this->hasMany(SerialUnitComponent::class, 'serial_unit_id');
    }

    /** Where this unit sits as a component of another (a sub-assembly's parents). */
    public function installedIn(): HasMany
    {
        return $this->hasMany(SerialUnitComponent::class, 'component_serial_unit_id');
    }

    public function history(): HasMany
    {
        // By id within one moment: a hold is stamped with the fail that caused it.
        return $this->hasMany(UnitStepHistory::class)->orderBy('processed_at')->orderBy('id');
    }
}
