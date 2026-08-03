<?php

namespace App\Services\Warehouse;

use App\Models\Material;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use App\Services\Material\StockMovementService;
use Illuminate\Support\Facades\DB;

/**
 * Keeps materials.stock_quantity in step with the per-warehouse balances (#212).
 *
 * Two views of the same stock exist: the global quantity that allocation, MRP and
 * the shortage reports read, and the per-warehouse balances warehousing works
 * with. Anything that sets warehouse balances from outside (an ERP snapshot, an
 * ERP lot list) must re-derive the global figure, or the two drift apart and an
 * issue posted later drives the global quantity negative while the warehouse
 * still shows stock.
 *
 * The difference is booked as an `adjustment` in the stock_movements ledger
 * rather than written silently, so the jump has an audit trail.
 */
class MaterialStockReconciler
{
    public function __construct(private StockMovementService $stockMovements) {}

    public function reconcile(int $materialId): void
    {
        DB::transaction(function () use ($materialId) {
            // Locked before the sum is taken: two concurrent syncs would otherwise
            // compute their delta from the same stale stock_quantity and apply both,
            // leaving the global figure adrift from the warehouse totals.
            $material = Material::where('id', $materialId)->lockForUpdate()->first();

            if (! $material) {
                return;
            }

            // Bulk rows only: lot rows are a breakdown of the same stock.
            $total = round((float) WarehouseStock::query()
                ->where('material_id', $materialId)
                ->whereNull('material_lot_id')
                ->sum('quantity'), 3);

            $delta = round($total - (float) $material->stock_quantity, 3);

            if (abs($delta) < 0.001) {
                $material->update(['last_stock_sync_at' => now()]);

                return;
            }

            $this->stockMovements->record(
                material: $material,
                movementType: StockMovement::TYPE_ADJUSTMENT,
                signedQuantity: $delta,
                sourceType: StockMovement::SOURCE_ERP_SYNC,
                reason: __('ERP stock sync'),
            );

            $material->update(['last_stock_sync_at' => now()]);
        });
    }

    /** @param  iterable<int>  $materialIds */
    public function reconcileMany(iterable $materialIds): void
    {
        foreach ($materialIds as $materialId) {
            $this->reconcile($materialId);
        }
    }
}
