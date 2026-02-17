<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Line extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'name',
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
     * Get the workstations for this line.
     */
    public function workstations(): HasMany
    {
        return $this->hasMany(Workstation::class);
    }

    /**
     * Get the work orders for this line.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get the users assigned to this line.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'line_user');
    }

    /**
     * Scope to get only active lines.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
