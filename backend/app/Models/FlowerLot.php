<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlowerLot extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_ARRIVED = 'ARRIVED';
    public const STATUS_CURING = 'CURING';
    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_ALLOCATED = 'ALLOCATED';
    public const STATUS_SCRAPPED = 'SCRAPPED';
    public const STATUS_DOWNGRADED = 'DOWNGRADED';

    /** Computed from usable_until, never stored. */
    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUSES = [
        self::STATUS_ARRIVED => '到厂',
        self::STATUS_CURING => '养护',
        self::STATUS_AVAILABLE => '可用',
        self::STATUS_ALLOCATED => '已付窨',
        self::STATUS_EXPIRED => '超窗',
        self::STATUS_SCRAPPED => '报废',
        self::STATUS_DOWNGRADED => '降级',
    ];

    protected $fillable = [
        'code',
        'variety',
        'origin',
        'supplier_name',
        'picked_at',
        'arrived_at',
        'usable_from',
        'usable_until',
        'weight_kg',
        'opening_rate',
        'curing_temperature',
        'storage_location',
        'status',
        'notes',
        'disposition_reason',
        'disposed_at',
        'disposed_by_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'picked_at' => 'datetime',
            'arrived_at' => 'datetime',
            'usable_from' => 'datetime',
            'usable_until' => 'datetime',
            'weight_kg' => 'decimal:3',
            'opening_rate' => 'decimal:2',
            'curing_temperature' => 'decimal:2',
            'disposed_at' => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FlowerLotAllocation::class);
    }

    public function disposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by_id');
    }

    /**
     * A lot past its cutoff is expired even if nobody wrote that status.
     * Scrapped, downgraded and fully allocated lots keep their terminal state.
     */
    public function effectiveStatus(): string
    {
        if (in_array($this->status, [self::STATUS_SCRAPPED, self::STATUS_DOWNGRADED, self::STATUS_ALLOCATED], true)) {
            return $this->status;
        }

        if ($this->usable_until && $this->usable_until->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->effectiveStatus()] ?? $this->effectiveStatus();
    }

    public function isExpired(): bool
    {
        return $this->effectiveStatus() === self::STATUS_EXPIRED;
    }

    public function remainingSeconds(): int
    {
        return $this->usable_until ? (int) now()->diffInSeconds($this->usable_until, false) : 0;
    }

    /** Expired / critical (<4h) / warning (4–8h) / safe (>8h). */
    public function urgency(): string
    {
        $seconds = $this->remainingSeconds();

        if ($seconds <= 0) {
            return 'expired';
        }

        if ($seconds < 4 * 3600) {
            return 'critical';
        }

        if ($seconds < 8 * 3600) {
            return 'warning';
        }

        return 'safe';
    }

    public function allocatedWeight(): float
    {
        return (float) $this->allocations->sum('weight_kg');
    }

    public function remainingWeight(): float
    {
        return max(0, (float) $this->weight_kg - $this->allocatedWeight());
    }
}
