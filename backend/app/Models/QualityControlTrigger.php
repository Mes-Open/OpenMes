<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable quality-control trigger (#105). When a production event matches
 * the trigger's type + scope, a QualityControlTask is created prompting the
 * referenced control (a QualityCheckTemplate). `is_blocking` turns the prompt
 * into a hard gate (production can't continue until the control is done).
 */
class QualityControlTrigger extends Model
{
    use Auditable, HasFactory;
    use SoftDeletesWithAudit;

    const TYPE_IN_PRODUCTION = 'in_production';

    const TYPE_EVERY_N_UNITS = 'every_n_units';

    const TYPE_EVERY_N_MINUTES = 'every_n_minutes';

    const TYPE_AFTER_DOWNTIME = 'after_downtime';

    const TYPE_AFTER_SETUP = 'after_setup';

    const TYPE_ROAMING = 'roaming';

    public const TYPES = [
        self::TYPE_IN_PRODUCTION,
        self::TYPE_EVERY_N_UNITS,
        self::TYPE_EVERY_N_MINUTES,
        self::TYPE_AFTER_DOWNTIME,
        self::TYPE_AFTER_SETUP,
        self::TYPE_ROAMING,
    ];

    /** Trigger types that require a positive `threshold_n`. */
    public const FREQUENCY_TYPES = [self::TYPE_EVERY_N_UNITS, self::TYPE_EVERY_N_MINUTES];

    protected $fillable = [
        'name',
        'trigger_type',
        'quality_check_template_id',
        'line_id',
        'workstation_id',
        'product_type_id',
        'threshold_n',
        'downtime_min_minutes',
        'is_blocking',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_n' => 'integer',
            'downtime_min_minutes' => 'integer',
            'is_blocking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function softDeleteCascades(): array
    {
        return [
            [QualityControlTask::class, 'quality_control_trigger_id'],
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QualityCheckTemplate::class, 'quality_check_template_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(QualityControlTask::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('trigger_type', $type);
    }

    /**
     * Does this trigger's scope (line / workstation / product type) match the
     * given batch? A null scope column means "any", so it always matches.
     */
    public function matchesBatch(Batch $batch): bool
    {
        $workOrder = $batch->workOrder;

        if ($this->line_id !== null && $this->line_id !== $workOrder?->line_id) {
            return false;
        }

        if ($this->workstation_id !== null && $this->workstation_id !== $batch->workstation_id) {
            return false;
        }

        if ($this->product_type_id !== null && $this->product_type_id !== $workOrder?->product_type_id) {
            return false;
        }

        return true;
    }
}
