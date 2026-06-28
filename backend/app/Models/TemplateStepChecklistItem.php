<?php

namespace App\Models;

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
}
