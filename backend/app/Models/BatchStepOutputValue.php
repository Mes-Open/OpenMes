<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The value an operator recorded for a typed step output on a specific batch
 * step. Exactly one typed column is set per the output's value_type; a `picture`
 * output stores the file on the private disk. A live row = recorded; clearing
 * soft-deletes it (audit preserved) so it can be re-recorded.
 */
class BatchStepOutputValue extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    /** Expose the authenticated picture URL to the operator/API serialization. */
    protected $appends = ['file_url'];

    protected $fillable = [
        'batch_step_id',
        'output_id',
        'value_text',
        'value_number',
        'value_boolean',
        'value_date',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'recorded_by_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_boolean' => 'boolean',
            'value_date' => 'date',
            'file_size' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    public function output(): BelongsTo
    {
        return $this->belongsTo(TemplateStepOutput::class, 'output_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /** Authenticated URL to the recorded picture (null for non-picture values). */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? route('operator.batch-step-output.file', $this) : null;
    }

    /**
     * The plain recorded value for API/UI, resolved from the output's type.
     * Pictures return null here (fetched via the file endpoint instead).
     */
    public function typedValue(): mixed
    {
        return match ($this->output?->value_type) {
            TemplateStepOutput::TYPE_NUMBER => $this->value_number === null ? null : (float) $this->value_number,
            TemplateStepOutput::TYPE_BOOLEAN => $this->value_boolean,
            TemplateStepOutput::TYPE_DATE => $this->value_date?->toDateString(),
            TemplateStepOutput::TYPE_PICTURE => null,
            default => $this->value_text,
        };
    }
}
