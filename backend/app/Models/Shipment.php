<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One load leaving the plant (#400): the truck, where it goes, and the list of
 * pallets and cartons it must carry. The dock scans each label against that list
 * (ShipmentLoadingService); confirming ships what was loaded.
 */
class Shipment extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    /**
     * Load IDs and label numbers: letters, digits and - _ / . only. Shared by
     * every Form Request that accepts one, so the web form, the REST API and the
     * ERP endpoint agree on what a valid identifier is.
     */
    public const IDENTIFIER_REGEX = '/^[A-Za-z0-9][A-Za-z0-9\-_\/.]*$/';

    public const IDENTIFIER_MAX = 64;

    /** Most expected units one shipment may list. */
    public const MAX_ITEMS = 500;

    protected $fillable = [
        'shipment_no',
        'erp_reference',
        'carrier',
        'vehicle_no',
        'destination',
        'planned_at',
        'notes',
        'status',
        'started_at',
        'started_by_id',
        'confirmed_at',
        'confirmed_by_id',
        'short_reason',
        'created_by_id',
        'tenant_id',
    ];

    protected $attributes = [
        'status' => 'planned',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'planned_at' => 'datetime',
            'started_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function softDeleteCascades(): array
    {
        return [
            [ShipmentItem::class, 'shipment_id'],
            [ShipmentScan::class, 'shipment_id'],
        ];
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ShipmentScan::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
