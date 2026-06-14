<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Site extends Model
{
    use Auditable, HasCustomFields, HasFactory, HasTenant;
    use SoftDeletesWithAudit;

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

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\Area::class, 'site_id'],
        ];
    }
}
