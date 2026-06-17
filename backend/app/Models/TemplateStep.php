<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateStep extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    public $timestamps = false;

    protected $fillable = [
        'process_template_id',
        'process_segment_id',
        'step_number',
        'name',
        'instruction',
        'estimated_duration_minutes',
        'min_duration_minutes',
        'requires_confirmation',
        'workstation_id',
        'is_optional',
        'variant_group',
        'is_default_variant',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'estimated_duration_minutes' => 'integer',
            'min_duration_minutes' => 'integer',
            'requires_confirmation' => 'boolean',
            'is_optional' => 'boolean',
            'is_default_variant' => 'boolean',
        ];
    }

    /**
     * Get the process template that owns this step.
     */
    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    /**
     * Get the workstation for this step.
     */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /**
     * Optional Process Segment (ISA-95) this step references for its defaults.
     */
    public function processSegment(): BelongsTo
    {
        return $this->belongsTo(ProcessSegment::class);
    }

    /**
     * Reference photo(s) attached to this specific step. Currently one per step.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProcessTemplatePhoto::class);
    }

    /**
     * Resolve the effective instruction — the step's own value overrides, but if
     * empty we fall back to the linked Process Segment's standard instruction.
     */
    public function effectiveInstruction(): ?string
    {
        return $this->instruction ?? $this->processSegment?->standard_instruction;
    }

    /**
     * Resolve the effective estimated duration — step value wins; otherwise
     * fall back to the linked Process Segment's default.
     */
    public function effectiveDuration(): ?int
    {
        return $this->estimated_duration_minutes ?? $this->processSegment?->estimated_duration_minutes;
    }
}
