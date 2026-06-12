<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionPlan extends Model
{
    use HasFactory;
    use HasTenant;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'description',
        'material_id',
        'material_type_id',
        'criteria',
        'is_active',
        'version',
        'published_at',
        'root_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    /** The root of this plan's version group (itself when it is the root). */
    public function root(): BelongsTo
    {
        return $this->belongsTo(InspectionPlan::class, 'root_id');
    }

    /** All versions belonging to the same group, including this one. */
    public function versions(): HasMany
    {
        return $this->hasMany(InspectionPlan::class, 'root_id', 'root_id');
    }

    /** Inspections performed against this exact plan version. */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    // ── Version-group helpers ────────────────────────────────────────────

    /** Id that identifies this plan's version group. */
    public function rootId(): int
    {
        return $this->root_id ?? $this->id;
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Every version row in this plan's group (root + children), newest first. */
    public function versionGroup()
    {
        $rootId = $this->rootId();

        return static::where('id', $rootId)
            ->orWhere('root_id', $rootId)
            ->orderByDesc('version');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Plans usable for an inspection of the given material — the live,
     * published version (is_active + published) matching the material, its
     * type, or a generic plan. Drafts are never offered.
     */
    public function scopeApplicableTo($query, Material $material)
    {
        return $query->active()->published()->where(function ($q) use ($material) {
            $q->where('material_id', $material->id)
                ->orWhere('material_type_id', $material->material_type_id)
                ->orWhere(function ($q2) {
                    $q2->whereNull('material_id')->whereNull('material_type_id');
                });
        });
    }
}
