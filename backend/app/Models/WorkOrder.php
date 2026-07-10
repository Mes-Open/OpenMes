<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use Auditable, HasCustomFields, HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    /**
     * Recompute the automatic priority score from the configured rules whenever
     * a work order is saved (create or update). No-op until at least one active
     * priority rule exists, so manual priorities are preserved until scoring is
     * configured. Runs with persist=false because a save is already in flight.
     */
    protected static function booted(): void
    {
        static::saving(function (self $workOrder): void {
            // Recompute only when a scoring-relevant field changed (or the row is
            // new); other saves — status transitions, produced_qty ticks — skip
            // the rule query. Time-based rules are kept fresh by priority:recalculate.
            if ($workOrder->exists
                && ! $workOrder->isDirty(['customer_id', 'planned_qty', 'due_date'])) {
                return;
            }

            app(\App\Services\WorkOrder\PriorityScoringService::class)->apply($workOrder, persist: false);
        });

        // Roll a completed order into its customer's aggregate metrics (order
        // count, revenue, auto tier promotion), once per order. Covers both a
        // transition into DONE (updated) and an order inserted already DONE
        // (created — e.g. a historical import). recordCompletion is idempotent.
        $accrue = function (self $workOrder): void {
            if ($workOrder->status === self::STATUS_DONE && ! $workOrder->customer_totals_counted) {
                app(\App\Services\Customer\CustomerMetricsService::class)->recordCompletion($workOrder);
            }
        };

        static::created($accrue);
        static::updated(function (self $workOrder) use ($accrue): void {
            if ($workOrder->wasChanged('status')) {
                $accrue($workOrder);
            }
        });
    }

    const STATUS_PENDING = 'PENDING';

    const STATUS_ACCEPTED = 'ACCEPTED';

    const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    const STATUS_BLOCKED = 'BLOCKED';

    const STATUS_PAUSED = 'PAUSED';

    const STATUS_DONE = 'DONE';

    const STATUS_REJECTED = 'REJECTED';

    const STATUS_CANCELLED = 'CANCELLED';

    /** Statuses that allow operators to work on the order */
    const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_IN_PROGRESS, self::STATUS_BLOCKED];

    /** Terminal statuses - no further transitions */
    const TERMINAL_STATUSES = [self::STATUS_DONE, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    protected $fillable = [
        'order_no',
        'customer_order_no',
        'customer_id',
        'line_id',
        'product_type_id',
        'process_snapshot',
        'planned_qty',
        'unit_price',
        'produced_qty',
        'status',
        'line_status_id',
        'priority',
        'priority_score',
        'customer_totals_counted',
        'due_date',
        'end_date',
        'week_number',
        'shift_number',
        'end_shift_number',
        'planned_start_at',
        'planned_end_at',
        'month_number',
        'production_year',
        'description',
        'extra_data',
        'completed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'process_snapshot' => 'array',
            'extra_data' => 'array',
            'planned_qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'produced_qty' => 'decimal:2',
            'priority' => 'integer',
            'priority_score' => 'integer',
            'customer_totals_counted' => 'boolean',
            'due_date' => 'datetime',
            'end_date' => 'datetime',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether this work order has minute-level planning timestamps
     * set on both ends. Used by the hourly schedule view to decide between
     * absolute time positioning and a legacy fallback (due_date-based block).
     */
    public function hasMinutePlanning(): bool
    {
        return $this->planned_start_at !== null && $this->planned_end_at !== null;
    }

    /**
     * Return the planned duration in whole minutes when minute planning is
     * set, otherwise null. Cross-midnight spans are reported as the raw
     * difference between the two timestamps without clamping.
     */
    public function plannedDurationMinutes(): ?int
    {
        if (! $this->hasMinutePlanning()) {
            return null;
        }

        return (int) $this->planned_start_at->diffInMinutes($this->planned_end_at, false);
    }

    /**
     * Get the customer this work order was placed for (nullable).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the line that owns this work order.
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Get the product type for this work order.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Get the line status (kanban status) for this work order.
     */
    public function lineStatus(): BelongsTo
    {
        return $this->belongsTo(LineStatus::class);
    }

    /**
     * Get the batches for this work order.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class)->orderBy('batch_number');
    }

    /** Pallets packed for this work order. */
    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    /**
     * Get the issues for this work order.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function eans(): HasMany
    {
        return $this->hasMany(WorkOrderEan::class);
    }

    /**
     * Get the scrap entries reported against this work order.
     */
    public function scrapEntries(): HasMany
    {
        return $this->hasMany(ScrapEntry::class);
    }

    /**
     * Manually-recorded additional costs booked against this work order.
     */
    public function additionalCosts(): HasMany
    {
        return $this->hasMany(AdditionalCost::class);
    }

    /**
     * Employee time blocks (tachograph activities) attributed to this work order.
     */
    public function employeeActivities(): HasMany
    {
        return $this->hasMany(EmployeeActivity::class);
    }

    /**
     * Material allocations (and their actual consumption) for this work order.
     */
    public function materialAllocations(): HasMany
    {
        return $this->hasMany(MaterialAllocation::class);
    }

    /**
     * Get the open blocking issues for this work order.
     */
    public function openBlockingIssues()
    {
        return $this->issues()
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->whereHas('issueType', function ($query) {
                $query->where('is_blocking', true);
            })
            ->get();
    }

    /**
     * Check if this work order is blocked by any open issues.
     */
    public function isBlocked(): bool
    {
        return $this->openBlockingIssues()->isNotEmpty();
    }

    /**
     * Check if this work order is complete.
     */
    public function isComplete(): bool
    {
        $allowOverproduction = config('openmmes.allow_overproduction', false);

        if ($allowOverproduction) {
            return $this->produced_qty >= $this->planned_qty;
        }

        return (float) $this->produced_qty >= (float) $this->planned_qty;
    }

    /**
     * Total scrap quantity reported against this work order.
     *
     * Reads from the loaded relation when available (avoids an extra query
     * after eager loading) and otherwise aggregates in the database.
     */
    public function totalScrapQty(): float
    {
        if ($this->relationLoaded('scrapEntries')) {
            return (float) $this->scrapEntries->sum('quantity');
        }

        return (float) $this->scrapEntries()->sum('quantity');
    }

    /**
     * Quality metric for this work order: the share of produced units that
     * were not scrapped, as a percentage. Scrap entries automatically pull
     * this down. Returns null when nothing has been produced yet.
     */
    public function qualityPct(): ?float
    {
        $produced = (float) $this->produced_qty;

        if ($produced <= 0) {
            return null;
        }

        $good = max(0.0, $produced - $this->totalScrapQty());

        return round(($good / $produced) * 100, 2);
    }

    /**
     * Scope to filter by status. Accepts a single value or an array — mobile
     * uses `?status[]=A&status[]=B` to match multiple statuses at once.
     */
    public function scopeStatus($query, string|array $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }

        return $query->where('status', $status);
    }

    /**
     * Scope to filter by line.
     */
    public function scopeForLine($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    /**
     * Scope to get work orders for a specific user's assigned lines.
     */
    public function scopeForUser($query, User $user)
    {
        // Admins and Supervisors see all work orders regardless of line assignment
        if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
            return $query;
        }

        $lineIds = $user->lines()->pluck('lines.id');

        return $query->whereIn('line_id', $lineIds);
    }

    /**
     * Scope to order by priority and due date.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority_score', 'desc')
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Scope to filter by packable state.
     * Packable work orders must be in DONE or IN_PROGRESS state.
     */
    public function scopePackable($query)
    {
        return $query->whereIn('status', [self::STATUS_DONE, self::STATUS_IN_PROGRESS]);
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\Batch::class, 'work_order_id'],
            [\App\Models\WorkOrderEan::class, 'work_order_id'],
            [\App\Models\AdditionalCost::class, 'work_order_id'],
            [\App\Models\ScrapEntry::class, 'work_order_id'],
            [\App\Models\WorkOrderShiftEntry::class, 'work_order_id'],
            [\App\Models\Issue::class, 'work_order_id'],
            [\App\Models\Pallet::class, 'work_order_id'],
        ];
    }
}
