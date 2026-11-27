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
use Illuminate\Support\Facades\Storage;

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
        // ERP classification + source identity (#212), filled by an ERP import.
        'category',
        'external_code',
        'external_system',
        'unit_of_measure',
        // Shelf life (e.g. 24 months): the batch expiry is creation day + this.
        'shelf_life_value',
        'shelf_life_unit',
        'is_active',
        'tenant_id',
    ];

    public const SHELF_LIFE_DAYS = 'days';

    public const SHELF_LIFE_MONTHS = 'months';

    public const SHELF_LIFE_UNITS = [self::SHELF_LIFE_DAYS, self::SHELF_LIFE_MONTHS];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'shelf_life_value' => 'integer',
        ];
    }

    public function hasShelfLife(): bool
    {
        return $this->shelf_life_value > 0 && in_array($this->shelf_life_unit, self::SHELF_LIFE_UNITS, true);
    }

    /**
     * Expiry day of something of this product produced on $productionDate, or
     * null without a shelf life. Months do not overflow: 31 January + 1 month
     * is 28/29 February, not 3 March.
     */
    public function expiryFrom(\Carbon\CarbonInterface $productionDate): ?\Carbon\CarbonInterface
    {
        if (! $this->hasShelfLife()) {
            return null;
        }

        $date = $productionDate->copy()->startOfDay();

        return $this->shelf_life_unit === self::SHELF_LIFE_MONTHS
            ? $date->addMonthsNoOverflow($this->shelf_life_value)
            : $date->addDays($this->shelf_life_value);
    }

    /**
     * Units of measure that count whole pieces. A product measured in one of
     * these (or with no unit at all — the default is pcs) cannot be logged,
     * scrapped or batched in fractions; kg, m, l and the like can.
     */
    public const COUNTED_UNITS = ['pcs', 'pc', 'piece', 'pieces', 'ea', 'each', 'unit', 'units', 'szt', 'szt.', 'sztuka', 'sztuk'];

    public function isCountedInPieces(): bool
    {
        $unit = mb_strtolower(trim((string) $this->unit_of_measure));

        return $unit === '' || in_array($unit, self::COUNTED_UNITS, true);
    }

    protected static function booted(): void
    {
        // Only drop the photo on a permanent delete — a soft delete is
        // reversible, and a restore from Trash should come back whole.
        static::forceDeleted(function (self $productType) {
            if ($productType->image_path) {
                Storage::delete($productType->image_path);
            }
        });
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

    /** Per-warehouse finished-goods balances for this product (#212). */
    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * Authenticated stream URL for the product photo, or null when there is
     * none. The stored file is never web-reachable directly.
     *
     * The URL is `/{id}/image` whatever the photo is, so it carries the head
     * of the file's random name as a cache buster: a replacement busts the
     * browser's private hour-long copy, while renaming the product — or any
     * other edit to the row — leaves the cached image alone.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return route('admin.product-types.image', $this)
            .'?v='.substr(pathinfo($this->image_path, PATHINFO_FILENAME), 0, 8);
    }
}
