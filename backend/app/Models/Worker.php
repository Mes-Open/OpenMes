<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Worker extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'personnel_class_id',
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
     * ISA-95 Personnel Class (competency template) the worker is enrolled in.
     */
    public function personnelClass(): BelongsTo
    {
        return $this->belongsTo(PersonnelClass::class);
    }

    /**
     * Get the user account linked to this worker.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the skills (certifications) for this worker.
     *
     * The pivot also exposes the legacy `level` proficiency field for
     * backward compatibility with the original worker_skills schema.
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'worker_skills')
            ->withPivot([
                'level',
                'cert_level',
                'certified_from',
                'certified_until',
                'certified_by_id',
                'cert_notes',
            ])
            ->withTimestamps();
    }

    /**
     * Skills whose certification expires within `$daysAhead` days but is not yet
     * expired. Skills with no certified_until are treated as never-expiring and
     * are excluded.
     */
    public function expiringSkills(int $daysAhead = 30): Collection
    {
        $today = now()->toDateString();
        $cut   = now()->addDays($daysAhead)->toDateString();

        return $this->skills()
            ->wherePivotNotNull('certified_until')
            ->wherePivot('certified_until', '>=', $today)
            ->wherePivot('certified_until', '<=', $cut)
            ->get();
    }

    /**
     * Skills whose certification window has already lapsed.
     */
    public function expiredSkills(): Collection
    {
        $today = now()->toDateString();

        return $this->skills()
            ->wherePivotNotNull('certified_until')
            ->wherePivot('certified_until', '<', $today)
            ->get();
    }

    /**
     * Scope to get only active workers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
