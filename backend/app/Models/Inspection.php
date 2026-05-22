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

    public const DISPOSITION_PENDING = 'pending';
    public const DISPOSITION_ACCEPT = 'accept';
    public const DISPOSITION_ACCEPT_WITH_DEVIATION = 'accept_with_deviation';
    public const DISPOSITION_REWORK = 'rework';
    public const DISPOSITION_SCRAP = 'scrap';
    public const DISPOSITION_RETURN_TO_SUPPLIER = 'return_to_supplier';
    public const DISPOSITION_QUARANTINE = 'quarantine';
    public const DISPOSITION_REJECT = 'reject';

    public const DISPOSITIONS = [
        self::DISPOSITION_PENDING,
        self::DISPOSITION_ACCEPT,
        self::DISPOSITION_ACCEPT_WITH_DEVIATION,
        self::DISPOSITION_REWORK,
        self::DISPOSITION_SCRAP,
        self::DISPOSITION_RETURN_TO_SUPPLIER,
        self::DISPOSITION_QUARANTINE,
        self::DISPOSITION_REJECT,
    ];

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
        'disposition',
        'disposition_notes',
        'disposition_by_id',
        'disposition_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'quantity_received' => 'decimal:3',
            'disposition_at' => 'datetime',
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

    public function dispositionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAIL;
    }

    public function hasDecision(): bool
    {
        return $this->disposition && $this->disposition !== self::DISPOSITION_PENDING;
    }

    public function isAccepted(): bool
    {
        return in_array($this->disposition, [
            self::DISPOSITION_ACCEPT,
            self::DISPOSITION_ACCEPT_WITH_DEVIATION,
        ], true);
    }

    public function isRejected(): bool
    {
        return in_array($this->disposition, [
            self::DISPOSITION_REJECT,
            self::DISPOSITION_SCRAP,
            self::DISPOSITION_RETURN_TO_SUPPLIER,
        ], true);
    }
}
