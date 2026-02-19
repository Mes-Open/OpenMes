<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WageGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'base_hourly_rate',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'base_hourly_rate' => 'decimal:4',
        ];
    }

    /**
     * Get the workers in this wage group.
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    /**
     * Scope to get only active wage groups.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
