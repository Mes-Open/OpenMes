<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoaDocument extends Model
{
    use Auditable, HasFactory, HasTenant;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_QA_REVIEW = 'QA_REVIEW';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_ISSUED = 'ISSUED';

    public const STATUS_VOID = 'VOID';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_QA_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_ISSUED,
        self::STATUS_VOID,
    ];

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'coa_number',
        'status',
        'current_version',
        'customer_id',
        'customer_code',
        'customer_name',
        'product_type_id',
        'product_code',
        'product_name',
        'product_revision_id',
        'revision_code',
        'lot_number',
        'specification_snapshot',
        'quality_snapshot',
        'gate_snapshot',
        'prepared_by_id',
        'prepared_at',
        'submitted_at',
        'approved_by_id',
        'approved_at',
        'issued_by_id',
        'issued_at',
        'voided_by_id',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'current_version' => 'integer',
            'specification_snapshot' => 'array',
            'quality_snapshot' => 'array',
            'gate_snapshot' => 'array',
            'prepared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CoaDocumentVersion::class)->orderBy('version');
    }

    public function latestVersion(): HasMany
    {
        return $this->hasMany(CoaDocumentVersion::class)->orderByDesc('version');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ElectronicSignature::class, 'signable_id')
            ->where('signable_type', self::class)
            ->orderByDesc('signed_at');
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
