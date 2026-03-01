<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Worker extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'crew_id',
        'wage_group_id',
        'workstation_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the workstation this worker is assigned to.
     */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /**
     * Get the crew this worker belongs to.
     */
    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    /**
     * Get the wage group for this worker.
     */
    public function wageGroup(): BelongsTo
    {
        return $this->belongsTo(WageGroup::class);
    }

    /**
     * Get the user account linked to this worker.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the skills for this worker.
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'worker_skills')
            ->withPivot('level');
    }

    /**
     * Scope to get only active workers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
