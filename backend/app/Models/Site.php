<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Site extends Model
{
    use HasFactory, HasTenant, Auditable;

    protected $fillable = [
        'name',
        'code',
        'company_id',
        'description',
        'address',
        'city',
        'country',
        'timezone',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    /**
     * Get all lines under this site through its areas.
     */
    public function lines(): HasManyThrough
    {
        return $this->hasManyThrough(Line::class, Area::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
