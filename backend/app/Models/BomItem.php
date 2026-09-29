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

    /** What `quantity_per_unit` is counted per: a finished unit, or a carton / pallet of a packing step. */
    public const PER_UNIT = 'unit';

    public const PER_CARTON = 'carton';

    public const PER_PALLET = 'pallet';

    public const PER = [self::PER_UNIT, self::PER_CARTON, self::PER_PALLET];

    protected $fillable = [
        'process_template_id',
        'template_step_id',
        'material_id',
        'product_type_id',
        'quantity_per_unit',
        'per',
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
     * How many finished units one "per" unit of this line covers: 1 for a
     * per-unit line, the carton size for a per-carton line, the carton size
     * times the cartons per pallet for a per-pallet line - read from the
     * packing step the line is attached to. A step without the capacity set
     * cannot convert, so the quantity is taken as per unit.
     */
    public function basisDivisor(): float
    {
        if ($this->per === self::PER_UNIT || $this->per === null) {
            return 1.0;
        }
        $config = $this->templateStep?->config ?? [];
        $carton = (float) ($config['carton_capacity'] ?? 0);
        $pallet = (float) ($config['pallet_capacity'] ?? 0);
        if ($this->per === self::PER_CARTON) {
            return $carton > 0 ? $carton : 1.0;
        }
        // Per pallet: cartons of units, or loose units straight onto the pallet.
        $unitsPerPallet = ($config['unit'] ?? 'carton') === 'pallet' ? $pallet : $carton * $pallet;

        return $unitsPerPallet > 0 ? $unitsPerPallet : 1.0;
    }

    /** The line's quantity expressed per finished unit - what planning, allocation and backflush work with. */
    public function perUnitQuantity(): float
    {
        return round((float) $this->quantity_per_unit / $this->basisDivisor(), 6);
    }

    /**
     * Calculate required quantity including scrap for given production quantity.
     */
    public function calculateRequiredQuantity(float $productionQty): float
    {
        $base = $this->perUnitQuantity() * $productionQty;
        $scrap = $base * ($this->scrap_percentage / 100);

        return round($base + $scrap, 4);
    }
}
