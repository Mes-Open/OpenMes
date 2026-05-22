<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ISA-95 Process Segment — reusable operation definition independent of product.
 *
 * Process segments describe the canonical "what" of an operation (e.g. "Injection
 * Molding 60s cycle", "Visual Inspection"). ProcessTemplate steps may reference a
 * segment via `template_steps.process_segment_id` to inherit standard
 * instruction, duration and skill requirements.
 */
class ProcessSegment extends Model
{
    use HasFactory, HasTenant, Auditable;

    public const TYPE_PRODUCTION  = 'production';
    public const TYPE_INSPECTION  = 'inspection';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_SETUP       = 'setup';
    public const TYPE_CLEANING    = 'cleaning';
    public const TYPE_TRANSPORT   = 'transport';
    public const TYPE_OTHER       = 'other';

    public const TYPES = [
        self::TYPE_PRODUCTION,
        self::TYPE_INSPECTION,
        self::TYPE_MAINTENANCE,
        self::TYPE_SETUP,
        self::TYPE_CLEANING,
        self::TYPE_TRANSPORT,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'segment_type',
        'workstation_type_id',
        'estimated_duration_minutes',
        'required_operators',
        'standard_instruction',
        'required_skill_ids',
        'parameters',
        'is_active',
        'created_by_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                  => 'boolean',
            'required_skill_ids'         => 'array',
            'parameters'                 => 'array',
            'estimated_duration_minutes' => 'integer',
            'required_operators'         => 'integer',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function workstationType(): BelongsTo
    {
        return $this->belongsTo(WorkstationType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function templateSteps(): HasMany
    {
        return $this->hasMany(TemplateStep::class, 'process_segment_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Eager helper for working with required_skill_ids JSON.
     */
    public function requiredSkills(): Collection
    {
        $ids = $this->required_skill_ids ?? [];
        if (empty($ids)) {
            return Skill::query()->whereRaw('1 = 0')->get();
        }
        return Skill::query()->whereIn('id', $ids)->get();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('segment_type', $type);
    }
}
