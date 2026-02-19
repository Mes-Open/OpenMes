<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'factory_id',
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
     * Get the factory that owns this division.
     */
    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    /**
     * Get the lines for this division.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Line::class);
    }

    /**
     * Get the crews for this division.
     */
    public function crews(): HasMany
    {
        return $this->hasMany(Crew::class);
    }

    /**
     * Scope to get only active divisions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
