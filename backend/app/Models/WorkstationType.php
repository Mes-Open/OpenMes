<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkstationType extends Model
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
     * Get the workstations of this type.
     */
    public function workstations(): HasMany
    {
        return $this->hasMany(Workstation::class);
    }

    /**
     * Get the tools associated with this workstation type.
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    /**
     * Scope to get only active workstation types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
