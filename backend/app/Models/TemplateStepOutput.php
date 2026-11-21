<?php

namespace App\Models;

use App\Models\Concerns\KeepsRecordedExecution;
use App\Models\Concerns\PropagatesTemplateChanges;
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
    use KeepsRecordedExecution;
    use PropagatesTemplateChanges;
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
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The output as frozen into a work order's process snapshot. Recorded values
     * keep pointing at this row's id, so the row is only ever soft-deleted.
     *
     * @return array<string, mixed>
     */
    public function toStepDefinition(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'value_type' => $this->value_type,
            'unit' => $this->unit,
            'options' => $this->options ?? [],
            'is_required' => (bool) $this->is_required,
            'sort_order' => (int) $this->sort_order,
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

    protected function recordedExecutionScope(): array
    {
        return ['checklist' => null, 'outputs' => self::query()->whereKey($this->id)];
    }
}
