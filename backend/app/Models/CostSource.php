<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'unit_cost',
        'unit',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'unit_cost'  => 'decimal:4',
        ];
    }

    /**
     * Get the additional costs using this cost source.
     */
    public function additionalCosts(): HasMany
    {
        return $this->hasMany(AdditionalCost::class);
    }

    /**
     * Get the maintenance events using this cost source.
     */
    public function maintenanceEvents(): HasMany
    {
        return $this->hasMany(MaintenanceEvent::class);
    }

    /**
     * Scope to get only active cost sources.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
