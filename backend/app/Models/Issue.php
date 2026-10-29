<?php

namespace App\Models;

use App\Enums\IssueDisposition;
use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Issue extends Model
{
    use Auditable, HasCustomFields, HasFactory;
    use SoftDeletesWithAudit;

    const STATUS_OPEN = 'OPEN';

    const STATUS_ACKNOWLEDGED = 'ACKNOWLEDGED';

    const STATUS_RESOLVED = 'RESOLVED';

    const STATUS_CLOSED = 'CLOSED';

    public const SOURCE_INBOUND_INSPECTION = 'inbound_inspection';

    public const SOURCE_IN_PROCESS = 'in_process';

    public const SOURCE_CUSTOMER_COMPLAINT = 'customer_complaint';

    // Non-conformance responsibility source (#11) — who is responsible for the
    // non-conformance. Distinct from `source` (where it originated).
    public const NC_SOURCE_INTERNAL = 'internal';

    public const NC_SOURCE_EXTERNAL = 'external';

    public const NC_SOURCE_SUPPLIER = 'supplier';

    public const NC_SOURCES = [self::NC_SOURCE_INTERNAL, self::NC_SOURCE_EXTERNAL, self::NC_SOURCE_SUPPLIER];

    protected $fillable = [
        'work_order_id',
        'production_downtime_id',
        'batch_step_id',
        'material_id',
        'source',
        'issue_type_id',
        'blocks_work_order',
        'title',
        'description',
        'status',
        'disposition',
        'non_conforming_qty',
        'root_cause',
        'containment_action',
        'nc_source',
        'disposition_by_id',
        'disposition_at',
        'reported_by_id',
        'assigned_to_id',
        'reported_at',
        'acknowledged_at',
        'resolved_at',
        'closed_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'disposition_at' => 'datetime',
            'non_conforming_qty' => 'decimal:2',
            'blocks_work_order' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Every place that files an issue without deciding its effect (operator
        // screens, the API, quality triggers, downtime escalation) keeps the old
        // behaviour: the type says whether it blocks. Only the Issues page
        // decides explicitly.
        static::creating(function (Issue $issue) {
            if (! array_key_exists('blocks_work_order', $issue->getAttributes())) {
                $issue->blocks_work_order = (bool) IssueType::whereKey($issue->issue_type_id)->value('is_blocking');
            }
        });
    }

    /**
     * Get the work order for this issue.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** The machine stop this was escalated from, if it came from one. */
    public function productionDowntime(): BelongsTo
    {
        return $this->belongsTo(ProductionDowntime::class);
    }

    /**
     * Get the batch step for this issue.
     */
    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    /**
     * Get the issue type.
     */
    public function issueType(): BelongsTo
    {
        return $this->belongsTo(IssueType::class);
    }

    /**
     * Get the user who reported this issue.
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /**
     * Get the user assigned to this issue.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Material referenced by this issue (set when source = inbound_inspection
     * or any non-WO context).
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * The user who set the current disposition (#11).
     */
    public function dispositionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_by_id');
    }

    /**
     * Corrective / preventive actions (CAPA) attached to this issue.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(IssueAction::class);
    }

    /**
     * Scrap recorded together with this issue, when it was filed with that effect.
     */
    public function scrapEntry(): HasOne
    {
        return $this->hasOne(ScrapEntry::class);
    }

    /**
     * Soft-deleting an issue also soft-deletes its CAPA actions (mirrors the
     * cascadeOnDelete FK, which doesn't fire on a soft delete).
     */
    public function softDeleteCascades(): array
    {
        return [
            [IssueAction::class, 'issue_id'],
        ];
    }

    /**
     * Check if this is a blocking issue.
     */
    public function isBlocking(): bool
    {
        return $this->blocks_work_order &&
            in_array($this->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]);
    }

    /**
     * Whether the issue has any non-verified CAPA action (which blocks closure).
     */
    public function hasUnverifiedActions(): bool
    {
        return $this->actions()->unverified()->exists();
    }

    /**
     * Scope to get open issues.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]);
    }

    /**
     * Scope to issues that are non-conformances rather than plain production
     * problems. There is no explicit flag column, so an issue counts as one
     * when anything marks it as such: it came from a quality source (inbound
     * inspection, in-process check, customer complaint), somebody classified
     * who is responsible (`nc_source`), recorded a non-conforming quantity, or
     * decided a disposition other than "pending".
     */
    public function scopeNonConformance($query)
    {
        return $query->where(fn ($q) => $q
            ->whereIn('source', [self::SOURCE_INBOUND_INSPECTION, self::SOURCE_IN_PROCESS, self::SOURCE_CUSTOMER_COMPLAINT])
            ->orWhereNotNull('nc_source')
            ->orWhere('non_conforming_qty', '>', 0)
            ->orWhere('disposition', '<>', IssueDisposition::Pending->value));
    }

    /**
     * Scope to get blocking issues.
     */
    public function scopeBlocking($query)
    {
        return $query->where('blocks_work_order', true)->open();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by disposition (#11).
     */
    public function scopeDisposition($query, string $disposition)
    {
        return $query->where('disposition', $disposition);
    }
}
