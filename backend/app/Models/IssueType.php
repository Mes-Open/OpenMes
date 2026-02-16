<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssueType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'severity',
        'is_blocking',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_blocking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the issues of this type.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Scope to get only active issue types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only blocking issue types.
     */
    public function scopeBlocking($query)
    {
        return $query->where('is_blocking', true);
    }
}
