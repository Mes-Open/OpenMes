<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workstation extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_id',
        'workstation_type_id',
        'code',
        'name',
        'workstation_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the line that owns this workstation.
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Get the workstation type for this workstation.
     */
    public function workstationType(): BelongsTo
    {
        return $this->belongsTo(WorkstationType::class);
    }

    /**
     * Get the template steps for this workstation.
     */
    public function templateSteps(): HasMany
    {
        return $this->hasMany(TemplateStep::class);
    }

    /**
     * Scope to get only active workstations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
