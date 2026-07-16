<?php

namespace App\Models;

use App\Enums\Tier;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'code',
        'tier',
        'payment_score',
        'total_orders',
        'total_revenue',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'payment_score' => 'integer',
            'total_orders' => 'integer',
            'total_revenue' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * When a scoring-relevant attribute changes, recompute priority for the
     * customer's active work orders (saveQuietly, so this doesn't recurse
     * through the work-order saving hook).
     */
    protected static function booted(): void
    {
        static::updated(function (self $customer): void {
            if (! $customer->wasChanged(['tier', 'payment_score', 'total_orders'])) {
                return;
            }

            $service = app(\App\Services\WorkOrder\PriorityScoringService::class);

            $customer->workOrders()
                ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
                ->get()
                ->each(fn (WorkOrder $workOrder) => $service->apply($workOrder, persist: true));
        });
    }

    /**
     * Work orders placed by this customer. Not cascaded on soft delete — the FK
     * nulls out instead (see the migration), so it is intentionally absent from
     * softDeleteCascades().
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** Scope to only active customers (e.g. for the work-order selector). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
