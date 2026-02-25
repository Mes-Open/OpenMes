<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductType extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'name',
        'description',
        'unit_of_measure',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the process templates for this product type.
     */
    public function processTemplates(): HasMany
    {
        return $this->hasMany(ProcessTemplate::class);
    }

    /**
     * Get the active process template for this product type.
     */
    public function activeProcessTemplate()
    {
        return $this->hasMany(ProcessTemplate::class)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Get the work orders for this product type.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get the lines this product type is assigned to.
     */
    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'line_product_type');
    }

    /**
     * Scope to get only active product types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
