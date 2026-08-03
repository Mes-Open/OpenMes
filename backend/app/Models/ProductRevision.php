<?php

namespace App\Models;

use App\Enums\RevisionLifecycle;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A formal, versioned released configuration of a product type (#180). Groups
 * the process template (BOM + steps) that applies to a manufactured revision.
 * Work orders reference a specific revision and snapshot it immutably at
 * creation, so releasing a newer revision never alters historical orders.
 */
class ProductRevision extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'product_type_id',
        'revision_code',
        'description',
        'lifecycle_status',
        'process_template_id',
        'change_reason',
        'external_ref',
        'effective_from',
        'effective_to',
        'released_at',
        'obsolete_at',
        'released_by_id',
    ];

    protected function casts(): array
    {
        return [
            'lifecycle_status' => RevisionLifecycle::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'released_at' => 'datetime',
            'obsolete_at' => 'datetime',
        ];
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    /**
     * Work orders produced under this revision. The FK nulls out on delete
     * (see migration), so it is intentionally not cascaded on soft delete.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function isDraft(): bool
    {
        return $this->lifecycle_status === RevisionLifecycle::Draft;
    }

    public function isReleased(): bool
    {
        return $this->lifecycle_status === RevisionLifecycle::Released;
    }

    /** Only released revisions may be selected for new production orders. */
    public function isSelectable(): bool
    {
        return $this->lifecycle_status?->isSelectable() ?? false;
    }

    /** Scope to revisions selectable for new work orders (released only). */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('lifecycle_status', RevisionLifecycle::Released->value);
    }
}
