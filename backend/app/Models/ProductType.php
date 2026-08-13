<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductType extends Model
{
    use HasCustomFields, HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    /**
     * `image_path` / `image_mime` are deliberately NOT fillable — they are
     * written only by the controller after ImageSanitizer has re-encoded the
     * upload, never from request input.
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'unit_of_measure',
        'is_active',
        'tenant_id',
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
    /**
     * Get the LOT sequence for this product type.
     */
    public function lotSequence(): HasOne
    {
        return $this->hasOne(LotSequence::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Authenticated stream URL for the product photo, or null when there is
     * none. The stored file is never web-reachable directly.
     *
     * `updated_at` is used as a cache buster so replacing the photo (the path
     * changes, but browsers may hold the old response for a private hour)
     * shows up immediately.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return route('admin.product-types.image', $this).'?v='.$this->updated_at?->timestamp;
    }
}
