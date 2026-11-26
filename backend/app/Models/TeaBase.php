<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeaBase extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_FROZEN = 'FROZEN';
    public const STATUS_RETIRED = 'RETIRED';

    public const QUALITY_PASS = 'PASS';
    public const QUALITY_FAIL = 'FAIL';
    public const QUALITY_PENDING = 'PENDING';

    public const STATUSES = [
        self::STATUS_PENDING => '待检',
        self::STATUS_AVAILABLE => '可用',
        self::STATUS_FROZEN => '冻结',
        self::STATUS_RETIRED => '停用',
    ];

    protected $fillable = [
        'code',
        'name',
        'material_id',
        'origin',
        'variety',
        'grade',
        'season',
        'screen_size',
        'supplier_name',
        'moisture_percent',
        'moisture_min',
        'moisture_max',
        'pesticide_value',
        'pesticide_limit',
        'pesticide_status',
        'heavy_metal_value',
        'heavy_metal_limit',
        'heavy_metal_status',
        'sensory_score',
        'sensory_standard',
        'sensory_status',
        'status',
        'last_inspected_at',
        'freeze_reason',
        'frozen_by_id',
        'frozen_at',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'moisture_percent' => 'decimal:2',
            'moisture_min' => 'decimal:2',
            'moisture_max' => 'decimal:2',
            'pesticide_value' => 'decimal:3',
            'pesticide_limit' => 'decimal:3',
            'heavy_metal_value' => 'decimal:3',
            'heavy_metal_limit' => 'decimal:3',
            'sensory_score' => 'decimal:2',
            'sensory_standard' => 'decimal:2',
            'last_inspected_at' => 'datetime',
            'frozen_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by_id');
    }

    /** Lots for the physical material this dossier describes. */
    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class, 'material_id', 'material_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /** Overall quality: one fail blocks, any pending withholds a clean pass. */
    public function qualityOverall(): string
    {
        $statuses = [$this->pesticide_status, $this->heavy_metal_status, $this->sensory_status];

        if (in_array(self::QUALITY_FAIL, $statuses, true)) {
            return self::QUALITY_FAIL;
        }

        if (in_array(self::QUALITY_PENDING, $statuses, true)) {
            return self::QUALITY_PENDING;
        }

        if ($this->moisture_percent === null) {
            return self::QUALITY_PENDING;
        }

        $moisture = (float) $this->moisture_percent;
        if ($moisture < (float) $this->moisture_min || $moisture > (float) $this->moisture_max) {
            return self::QUALITY_FAIL;
        }

        return self::QUALITY_PASS;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
