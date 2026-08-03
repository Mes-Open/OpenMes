<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item line of a stock document (#212). Quantity is always positive; the
 * parent document's direction() decides the sign applied to the balance.
 */
class StockDocumentLine extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'stock_document_id',
        'material_id',
        'product_type_id',
        'material_lot_id',
        'lot_number',
        'quantity',
        'unit_of_measure',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }

    /** Lot identity for display: the linked lot's number, else the free-text one. */
    public function effectiveLotNumber(): ?string
    {
        return $this->materialLot?->lot_number ?? $this->lot_number;
    }
}
