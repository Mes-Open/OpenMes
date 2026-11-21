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
 * A checklist item defined on a process template step (reusable). Completion is
 * recorded per batch step in batch_step_checklist_completions.
 */
class TemplateStepChecklistItem extends Model
{
    use HasFactory;
    use KeepsRecordedExecution;
    use PropagatesTemplateChanges;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'process_template_id',
        'template_step_id',
        'label',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The item as frozen into a work order's process snapshot. Recorded ticks keep
     * pointing at this row's id, so the row is only ever soft-deleted.
     *
     * @return array<string, mixed>
     */
    public function toStepDefinition(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
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

    public function completions(): HasMany
    {
        return $this->hasMany(BatchStepChecklistCompletion::class, 'checklist_item_id');
    }

    protected function recordedExecutionScope(): array
    {
        return ['checklist' => self::query()->whereKey($this->id), 'outputs' => null];
    }
}
