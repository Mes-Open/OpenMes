<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document attached to a production step for shop-floor document control
 * (an SOP, drawing, work instruction, certificate). When marked mandatory and
 * validatable, the step it belongs to cannot be completed until an operator
 * validates the document - the validation is recorded with who and when.
 */
class BatchStepDocument extends Model
{
    use Auditable, HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'batch_step_id',
        'name',
        'reference',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'is_mandatory',
        'requires_validation',
        'validated_at',
        'validated_by_id',
        'uploaded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'requires_validation' => 'boolean',
            'validated_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    /**
     * Does this document block its step's completion? A mandatory, validatable
     * document that has not been validated yet is blocking.
     */
    public function isBlocking(): bool
    {
        return $this->is_mandatory && $this->requires_validation && ! $this->isValidated();
    }

    /** Record who validated this document and when (idempotent). */
    public function markValidated(User $user): void
    {
        if ($this->isValidated()) {
            return;
        }

        $this->forceFill([
            'validated_at' => now(),
            'validated_by_id' => $user->id,
        ])->save();
    }

    /** Scope: documents that block step completion (mandatory + unvalidated). */
    public function scopeBlocking($query)
    {
        return $query->where('is_mandatory', true)
            ->where('requires_validation', true)
            ->whereNull('validated_at');
    }
}
