<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationConfig extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'system_type',
        'system_name',
        'api_config',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'api_config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function materialSources(): HasMany
    {
        return $this->hasMany(MaterialSource::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
