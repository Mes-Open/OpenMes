<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One material commitment on a purchase order.
 *
 * received quantity is not a column: it is the sum of the quantity on posted
 * stock-document lines that point at this purchase-order line. A cancelled
 * receipt therefore stops counting automatically.
 */
class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'material_id',
        'line_no',
        'quantity_ordered',
        'unit_price',
        'unit_of_measure',
        'expected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'expected_at' => 'date',
            'line_no' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class);
    }

    public function postedReceivedQuantity(): float
    {
        return (float) $this->receiptLines()
            ->whereHas('stockDocument', fn ($query) => $query->where('status', StockDocument::STATUS_POSTED))
            ->sum('quantity');
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->quantity_ordered - $this->postedReceivedQuantity());
    }
}
