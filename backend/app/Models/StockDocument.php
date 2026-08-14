<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Warehouse document (#212): material released to production, finished product
 * received from it, and the reverse of each.
 *
 * The type fixes the direction (see direction()) and which item column its lines
 * use, so a line never carries its own sign. Only a draft can be posted, and
 * only a posted document can be cancelled — posting/cancelling belongs to
 * StockDocumentService, which owns the balance and ledger side effects.
 */
class StockDocument extends Model
{
    use HasFactory;
    use HasTenant;
    use SoftDeletesWithAudit;

    /** Raw material leaving a warehouse for production (a "release"). */
    public const TYPE_MATERIAL_ISSUE = 'material_issue';

    /** Raw material arriving in a warehouse (ERP receipt, or a return from the shop floor). */
    public const TYPE_MATERIAL_RECEIPT = 'material_receipt';

    /** Finished product arriving in a warehouse when a work order is concluded. */
    public const TYPE_PRODUCT_RECEIPT = 'product_receipt';

    /** Finished product leaving a warehouse (shipment, correction). */
    public const TYPE_PRODUCT_ISSUE = 'product_issue';

    public const TYPES = [
        self::TYPE_MATERIAL_ISSUE,
        self::TYPE_MATERIAL_RECEIPT,
        self::TYPE_PRODUCT_RECEIPT,
        self::TYPE_PRODUCT_ISSUE,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_POSTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'document_no',
        'type',
        'status',
        'warehouse_id',
        'work_order_id',
        'batch_id',
        'notes',
        'erp_reference',
        'erp_synced_at',
        'posted_at',
        'posted_by_id',
        'created_by_id',
        'tenant_id',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'erp_synced_at' => 'datetime',
        ];
    }

    /** @return array<int, array{0: class-string, 1: string}> */
    public function softDeleteCascades(): array
    {
        return [
            [StockDocumentLine::class, 'stock_document_id'],
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** +1 when the document adds to the warehouse balance, -1 when it removes. */
    public function direction(): int
    {
        return match ($this->type) {
            self::TYPE_MATERIAL_RECEIPT, self::TYPE_PRODUCT_RECEIPT => 1,
            default => -1,
        };
    }

    /** Material-side documents carry material lines; product-side carry product types. */
    public function isMaterialDocument(): bool
    {
        return in_array($this->type, [self::TYPE_MATERIAL_ISSUE, self::TYPE_MATERIAL_RECEIPT], true);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    /** Documents the ERP has not acknowledged yet — the export backlog. */
    public function scopeNotSynced(Builder $query): Builder
    {
        return $query->whereNull('erp_synced_at');
    }

    /** The warehouse kind a document of this type must be posted against. */
    public static function warehouseKindFor(string $type): string
    {
        return in_array($type, [self::TYPE_MATERIAL_ISSUE, self::TYPE_MATERIAL_RECEIPT], true)
            ? Warehouse::KIND_RAW_MATERIAL
            : Warehouse::KIND_FINISHED_GOODS;
    }
}
