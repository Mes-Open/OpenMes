<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Area extends Model
{
    use HasFactory, HasTenant, Auditable;

    protected $fillable = [
        'name',
        'code',
        'site_id',
        'description',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(Line::class);
    }

    /**
     * Get all workstations under this area through its lines.
     */
    public function workstations(): HasManyThrough
    {
        return $this->hasManyThrough(Workstation::class, Line::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
