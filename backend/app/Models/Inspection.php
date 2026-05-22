<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_CONDITIONAL = 'conditional_pass';

    protected $fillable = [
        'inspection_plan_id',
        'material_id',
        'lot_number',
        'supplier_lot_ref',
        'quantity_received',
        'inspector_id',
        'started_at',
        'completed_at',
        'status',
        'notes',
        'issue_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'quantity_received' => 'decimal:3',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InspectionPlan::class, 'inspection_plan_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * Material lots whose acceptance / quarantine was decided by this inspection.
     */
    public function lotsTested(): HasMany
    {
        return $this->hasMany(MaterialLot::class, 'inspection_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAIL;
    }
}
