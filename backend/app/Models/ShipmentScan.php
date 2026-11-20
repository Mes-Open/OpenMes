<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of the dock: every code scanned against a shipment,
 * what it resolved to and what the terminal answered. Rows are never edited;
 * an undo is a new `removed` row. They leave only with their shipment.
 */
class ShipmentScan extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    public const RESULT_LOADED = 'loaded';

    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_UNEXPECTED = 'unexpected';

    public const RESULT_UNKNOWN = 'unknown';

    public const RESULT_BLOCKED = 'blocked';

    public const RESULT_REMOVED = 'removed';

    public const RESULTS = [
        self::RESULT_LOADED,
        self::RESULT_DUPLICATE,
        self::RESULT_UNEXPECTED,
        self::RESULT_UNKNOWN,
        self::RESULT_BLOCKED,
        self::RESULT_REMOVED,
    ];

    protected $fillable = [
        'shipment_id',
        'scanned_code',
        'unit_type',
        'unit_id',
        'shipment_item_id',
        'result',
        'message',
        'user_id',
        'workstation_id',
        'scanned_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'unit_id' => 'integer',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class, 'shipment_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
