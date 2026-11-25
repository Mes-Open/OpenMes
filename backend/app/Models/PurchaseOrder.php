<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A commitment to buy materials from one supplier.
 *
 * The order itself never moves stock. Receiving a line posts a material-receipt
 * stock document through StockDocumentService, and the received quantity is
 * derived from those posted documents rather than stored on the order — so a
 * cancelled receipt reverses the received quantity with no manual correction.
 */
class PurchaseOrder extends Model
{
    use Auditable;
    use HasFactory;
    use HasTenant;
    use SoftDeletesWithAudit;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'number',
        'supplier_id',
        'status',
        'ordered_at',
        'expected_at',
        'currency',
        'notes',
        'closed_at',
        'created_by_id',
        'tenant_id',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'currency' => 'CNY',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'expected_at' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no')->orderBy('id');
    }

    public function receiptDocuments(): HasMany
    {
        return $this->hasMany(StockDocument::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function orderedTotal(): float
    {
        return (float) $this->lines->sum(fn (PurchaseOrderLine $line) => (float) $line->quantity_ordered * (float) $line->unit_price);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->expected_at !== null
            && $this->expected_at->lt(today());
    }
}
