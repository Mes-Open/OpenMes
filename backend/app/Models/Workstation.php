<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workstation extends Model
{
    use HasFactory, Auditable;

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
     * Get the workers assigned to this workstation.
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    /**
     * Get the state history for this workstation.
     */
    public function states(): HasMany
    {
        return $this->hasMany(WorkstationState::class);
    }

    /**
     * Get the downtime events for this workstation.
     */
    public function downtimeEvents(): HasMany
    {
        return $this->hasMany(DowntimeEvent::class);
    }

    /**
     * Get the production cycles for this workstation.
     */
    public function productionCycles(): HasMany
    {
        return $this->hasMany(ProductionCycle::class);
    }

    /**
     * Get the quality events for this workstation.
     */
    public function qualityEvents(): HasMany
    {
        return $this->hasMany(QualityEvent::class);
    }

    /**
     * Get the current state of the workstation.
     */
    public function currentState(): ?WorkstationState
    {
        return $this->states()->whereNull('ended_at')->latest('started_at')->first();
    }

    /**
     * Scope to get only active workstations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
