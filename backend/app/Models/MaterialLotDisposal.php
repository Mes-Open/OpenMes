<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One physical removal from a quality-held material lot.
 *
 * A disposal is not just a status change: returning or scrapping a lot takes
 * stock out of the warehouse. Each row keeps the quantity, the stock document
 * that moved it and the user who decided, so the lot's held balance and the
 * warehouse ledger can be reconciled.
 */
class MaterialLotDisposal extends Model
{
    use HasTenant;

    public const DISPOSITION_SCRAP = 'scrap';

    public const DISPOSITION_RETURN = 'return_to_supplier';

    public const DISPOSITION_REJECT = 'reject';

    public const DISPOSITIONS = [
        self::DISPOSITION_SCRAP,
        self::DISPOSITION_RETURN,
        self::DISPOSITION_REJECT,
    ];

    protected $fillable = [
        'material_lot_id',
        'inspection_id',
        'disposition',
        'quantity',
        'stock_document_id',
        'notes',
        'disposed_by_id',
        'disposed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'disposed_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class);
    }

    public function disposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by_id');
    }
}
