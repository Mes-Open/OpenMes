<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'process_template_id',
        'template_step_id',
        'material_id',
        'product_type_id',
        'quantity_per_unit',
        'scrap_percentage',
        'consumed_at',
        'sort_order',
        'extra_data',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_unit' => 'decimal:4',
            'scrap_percentage' => 'decimal:2',
            'sort_order' => 'integer',
            'extra_data' => 'array',
        ];
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    public function templateStep(): BelongsTo
    {
        return $this->belongsTo(TemplateStep::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * A BOM line may reference a manufactured product type (sub-assembly)
     * instead of a material.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * 'material' or 'product_type' — which kind of component this line is.
     */
    public function getComponentKindAttribute(): string
    {
        return $this->product_type_id ? 'product_type' : 'material';
    }

    /**
     * Calculate required quantity including scrap for given production quantity.
     */
    public function calculateRequiredQuantity(float $productionQty): float
    {
        $base = $this->quantity_per_unit * $productionQty;
        $scrap = $base * ($this->scrap_percentage / 100);

        return round($base + $scrap, 4);
    }
}
