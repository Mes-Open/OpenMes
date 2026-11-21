<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One record of a pallet's physical condition: who found it in what state,
 * when, and on which occasion. Append-only - rows are never edited or deleted,
 * so the trail stays authoritative for audits.
 */
class PalletConditionEvent extends Model
{
    use HasTenant;

    /** Recorded with the pallet's first move into the warehouse. */
    public const SOURCE_RECEIPT = 'receipt';

    /** Recorded with a later move. */
    public const SOURCE_MOVE = 'move';

    /** Recorded on the pallet screen (marked damaged or restored). */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCES = [self::SOURCE_RECEIPT, self::SOURCE_MOVE, self::SOURCE_MANUAL];

    protected $fillable = [
        'pallet_id',
        'condition',
        'note',
        'user_id',
        'recorded_at',
        'source',
        'pallet_movement_id',
        'tenant_id',
    ];

    protected static function booted(): void
    {
        $refuse = static function (): void {
            throw new \LogicException('Pallet condition events are append-only.');
        };

        static::updating($refuse);
        static::deleting($refuse);
    }

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(PalletMovement::class, 'pallet_movement_id');
    }
}
