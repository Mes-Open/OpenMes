<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A uniquely identified physical unit (serial number). Its full process
 * history — every workstation it passed through, with operator and parameter
 * snapshots — lives in UnitStepHistory.
 */
class SerialUnit extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_IN_PRODUCTION = 'in_production';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SCRAPPED = 'scrapped';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUSES = [
        self::STATUS_IN_PRODUCTION,
        self::STATUS_COMPLETED,
        self::STATUS_SCRAPPED,
        self::STATUS_SHIPPED,
    ];

    protected $fillable = [
        'serial_no',
        'work_order_id',
        'batch_id',
        'material_id',
        'status',
        'produced_at',
        'tenant_id',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'produced_at' => 'datetime',
            'extra_data' => 'array',
        ];
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

    public function history(): HasMany
    {
        return $this->hasMany(UnitStepHistory::class)->orderBy('processed_at');
    }
}
