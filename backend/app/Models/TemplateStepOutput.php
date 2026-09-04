<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A typed operator-output defined on a process template step (reusable): the
 * operator records a value of this type at execution. Recorded values live per
 * batch step in batch_step_output_values. Mirrors TemplateStepChecklistItem.
 */
class TemplateStepOutput extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_SELECT = 'select';

    public const TYPE_DATE = 'date';

    public const TYPE_PICTURE = 'picture';

    public const VALUE_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_BOOLEAN,
        self::TYPE_SELECT,
        self::TYPE_DATE,
        self::TYPE_PICTURE,
    ];

    protected $fillable = [
        'process_template_id',
        'template_step_id',
        'key',
        'label',
        'value_type',
        'unit',
        'options',
        'is_required',
        'sort_order',
        'expected_min',
        'expected_max',
        'expected_value',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'expected_min' => 'decimal:4',
            'expected_max' => 'decimal:4',
        ];
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    public function templateStep(): BelongsTo
    {
        return $this->belongsTo(TemplateStep::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(BatchStepOutputValue::class, 'output_id');
    }

    /**
     * True when this output has a configured pass criterion. Callers (the
     * OutputGateEvaluator) treat "no criterion" as "always passes" — the
     * required-field gate (is_required) is unaffected either way.
     */
    public function hasExpectedResult(): bool
    {
        return $this->expected_min !== null
            || $this->expected_max !== null
            || $this->expected_value !== null;
    }
}
