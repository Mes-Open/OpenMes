<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workstation extends Model
{
    use HasCustomFields, HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'line_id',
        'workstation_type_id',
        'code',
        'name',
        'workstation_type',
        'ideal_rate_per_hour',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ideal_rate_per_hour' => 'decimal:2',
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
     * Get the workers assigned to this workstation.
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    /**
     * Time-sliced machine state history — the timeline behind the shift monitor
     * and the machine monitor's availability figures.
     */
    public function states(): HasMany
    {
        return $this->hasMany(WorkstationState::class);
    }

    /**
     * Scope to get only active workstations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
