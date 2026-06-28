<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that an operator ticked a step checklist item on a specific batch
 * step (who and when). Presence = checked; un-checking deletes the row.
 */
class BatchStepChecklistCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_step_id',
        'checklist_item_id',
        'checked_by_id',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(TemplateStepChecklistItem::class, 'checklist_item_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }
}
