<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlendFormula extends Model
{
    use HasFactory;
    use HasTenant;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SAMPLE = 'SAMPLE';
    public const STATUS_LARGE_SAMPLE = 'LARGE_SAMPLE';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_FROZEN = 'FROZEN';

    public const STATUSES = [
        self::STATUS_DRAFT => '草稿',
        self::STATUS_SAMPLE => '小样',
        self::STATUS_LARGE_SAMPLE => '大样',
        self::STATUS_APPROVED => '批准',
        self::STATUS_FROZEN => '冻结',
    ];

    public const RESULT_PASS = 'PASS';
    public const RESULT_FAIL = 'FAIL';
    public const RESULT_PENDING = 'PENDING';

    protected $fillable = [
        'code',
        'product_type_id',
        'customer_id',
        'version',
        'status',
        'standard_sample_code',
        'standard_sample_approved_at',
        'standard_sample_summary',
        'standard_sample_blind_result',
        'small_sample_result',
        'large_sample_result',
        'effective_date',
        'submitted_by_id',
        'submitted_at',
        'reviewed_by_id',
        'reviewed_at',
        'approved_by_id',
        'approved_at',
        'frozen_by_id',
        'frozen_at',
        'freeze_reason',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'standard_sample_approved_at' => 'datetime',
            'standard_sample_blind_result' => 'decimal:2',
            'effective_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'frozen_at' => 'datetime',
        ];
    }

    public function components(): HasMany
    {
        return $this->hasMany(BlendFormulaComponent::class)->orderBy('sort_order');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by_id');
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function hasStandardSample(): bool
    {
        return filled($this->standard_sample_code) && $this->standard_sample_approved_at !== null;
    }

    public function formulaTotal(): float
    {
        return (float) $this->components->sum('standard_ratio');
    }

    public function canFreeze(): bool
    {
        return in_array($this->status, [self::STATUS_SAMPLE, self::STATUS_LARGE_SAMPLE, self::STATUS_APPROVED], true);
    }
}
