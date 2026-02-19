<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subassembly extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'product_type_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the product type for this subassembly.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Scope to get only active subassemblies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
