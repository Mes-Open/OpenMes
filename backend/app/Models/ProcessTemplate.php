<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_type_id',
        'name',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * Get the product type that owns this process template.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Get the template steps for this process template.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TemplateStep::class)->orderBy('step_number');
    }

    /**
     * Generate a JSON snapshot of this template for work order storage.
     * This ensures work orders are immune to template changes.
     */
    public function toSnapshot(): array
    {
        return [
            'template_id' => $this->id,
            'template_name' => $this->name,
            'template_version' => $this->version,
            'steps' => $this->steps->map(function ($step) {
                return [
                    'step_number' => $step->step_number,
                    'name' => $step->name,
                    'instruction' => $step->instruction,
                    'estimated_duration_minutes' => $step->estimated_duration_minutes,
                    'workstation_id' => $step->workstation_id,
                    'workstation_name' => $step->workstation?->name,
                ];
            })->toArray(),
        ];
    }

    /**
     * Scope to get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
