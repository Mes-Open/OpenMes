<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchWeighingRecord extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_PASS = 'PASS';

    public const STATUS_FAIL = 'FAIL';

    public const STATUSES = [self::STATUS_PASS, self::STATUS_FAIL];

    public const DEFAULT_TOLERANCE_PCT = 0.500;

    protected $fillable = [
        'batch_id',
        'step_number',
        'material_id',
        'material_code',
        'material_name',
        'unit_of_measure',
        'target_qty',
        'actual_qty',
        'tolerance_pct',
        'variance_qty',
        'variance_pct',
        'status',
        'scale_code',
        'weighed_by_id',
        'weighed_at',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'target_qty' => 'decimal:4',
            'actual_qty' => 'decimal:4',
            'tolerance_pct' => 'decimal:3',
            'variance_qty' => 'decimal:4',
            'variance_pct' => 'decimal:3',
            'weighed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function weighedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'weighed_by_id');
    }

    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASS;
    }
}
