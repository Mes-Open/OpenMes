<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crew extends Model
{
    use Auditable, HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'code',
        'name',
        'leader_id',
        'division_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user who leads this crew.
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Get the division this crew belongs to.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the workers in this crew.
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    /**
     * Get the recurring break windows for this crew.
     */
    public function breakWindows(): HasMany
    {
        return $this->hasMany(CrewBreakWindow::class);
    }

    /**
     * Scope to get only active crews.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\CrewBreakWindow::class, 'crew_id'],
        ];
    }
}
