<?php

namespace App\Services\Material;

use App\Models\MaterialAllocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseStockService;
use Illuminate\Support\Facades\DB;

/**
 * Deducting shop-floor consumption from the location it was actually taken from.
 *
 * Allocation already removes material from the plant-wide `materials.stock_quantity`
 * and from the picked lot's available quantity. Neither of those says WHERE the
 * material physically was, so a plant running several stores could not tell which one
 * had emptied. This service closes that: when consumption is booked, the balance of
 * the location the material came off moves, and the movement is written to the
 * ledger with that location on it.
 *
 * It moves location balances ONLY. Touching global stock again here would double-count
 * against the allocation that already booked it.
 */
class ConsumptionLocationService
{
    public function __construct(
        private WarehouseStockService $warehouseStock,
        private StockMovementService $stockMovements,
    ) {}

    /**
     * Book consumption for an allocation against its location, moving the balance by
     * the difference between what is now consumed and what was already deducted.
     *
     * Called every time a consumed quantity is written — an operator's entry, a
     * correction, batch completion — so it must be the delta, not the total. A
     * downward correction produces a positive delta and credits the location back.
     *
     * Returns the movement it wrote, or null when there was nothing to move (no
     * location resolvable, or the quantity did not change).
     *
     * @throws \DomainException When the location lacks the stock and the plant blocks negative balances.
     */
    public function deduct(MaterialAllocation $allocation, float $consumedTotal, ?User $user = null): ?StockMovement
    {
        return DB::transaction(function () use ($allocation, $consumedTotal, $user) {
            // Re-read under a lock: `location_deducted_qty` is a read-modify-write, and
            // two operators booking on the same allocation would otherwise each deduct
            // against the same stale starting point.
            $locked = MaterialAllocation::where('id', $allocation->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return null;
            }

            $warehouse = $this->resolveWarehouse($locked);

            if ($warehouse === null) {
                // No location to attribute this to — a plant that does not track stock
                // per location. Global stock and the lot already moved at allocation,
                // so there is simply nothing further to do.
                return null;
            }

            $delta = round($consumedTotal - (float) $locked->location_deducted_qty, 4);

            if (abs($delta) < 0.00005) {
                return null;
            }

            $keys = [
                'warehouse_id' => $warehouse->id,
                'material_id' => $locked->material_id,
            ];

            // Read the balance once, before anything moves: it decides both whether the
            // deduction is refused and — when it is not — how big a shortfall to flag.
            $available = $this->warehouseStock->available($keys);

            $this->assertSufficient($locked, $warehouse, $delta, $available);

            // The lot-level balance and the material total for that location are kept
            // separately (#212), so a per-material view does not have to sum lots.
            foreach ($this->lotShares($locked, $delta) as $lotId => $lotDelta) {
                $this->warehouseStock->adjust([...$keys, 'material_lot_id' => $lotId], -$lotDelta);
            }

            $this->warehouseStock->adjust([...$keys, 'material_lot_id' => null], -$delta);

            $locked->update([
                'consumption_warehouse_id' => $warehouse->id,
                'location_deducted_qty' => round((float) $locked->location_deducted_qty + $delta, 4),
            ]);

            // Audit: one ledger row per deduction, carrying the location. `adjustGlobal`
            // is off because allocation already moved the plant-wide quantity — this
            // row exists to say which location gave the material up.
            return $this->stockMovements->record(
                material: $locked->material,
                movementType: $delta > 0 ? StockMovement::TYPE_CONSUME : StockMovement::TYPE_RETURN,
                signedQuantity: -$delta,
                user: $user,
                sourceType: $locked->batch_step_id
                    ? StockMovement::SOURCE_BATCH_STEP
                    : StockMovement::SOURCE_BATCH,
                sourceId: $locked->batch_step_id ?: $locked->batch_id,
                reason: $this->reason($locked, $delta, $available),
                warehouseId: $warehouse->id,
                adjustGlobal: false,
            );
        });
    }

    /**
     * Give back everything this allocation took off its location — used when a batch
     * is cancelled after consumption had already been booked.
     */
    public function reverse(MaterialAllocation $allocation, ?User $user = null): ?StockMovement
    {
        return $this->deduct($allocation, 0, $user);
    }

    /**
     * Which location this allocation's material comes off.
     *
     * Once something has been deducted the answer is frozen on the allocation: a
     * correction must credit back the location that actually gave the material up,
     * even if the lot has since moved or the line has been re-pointed elsewhere.
     *
     * Otherwise, most specific first — the picked lot knows exactly where it sits, the
     * line knows its own workshop store, and the plant default is the last resort.
     */
    public function resolveWarehouse(MaterialAllocation $allocation): ?Warehouse
    {
        if ($allocation->consumption_warehouse_id !== null) {
            return Warehouse::find($allocation->consumption_warehouse_id);
        }

        $fromLot = $allocation->lotPicks
            ->map(fn ($pick) => $pick->lot?->warehouse_id)
            ->filter()
            ->first();

        if ($fromLot) {
            return Warehouse::find($fromLot);
        }

        $fromLine = $allocation->batch?->workOrder?->line?->warehouse_id;

        if ($fromLine) {
            return Warehouse::find($fromLine);
        }

        return Warehouse::resolveDefault(Warehouse::KIND_RAW_MATERIAL);
    }

    /**
     * Split a deduction across the lots this allocation picked, proportionally to what
     * was picked from each, so a lot-level balance never drifts from the material
     * total above it. Returns [lot id => quantity] and is empty when nothing was
     * picked by lot.
     *
     * @return array<int, float>
     */
    private function lotShares(MaterialAllocation $allocation, float $delta): array
    {
        $picks = $allocation->lotPicks->filter(fn ($pick) => (float) $pick->picked_qty > 0);
        $total = (float) $picks->sum('picked_qty');

        if ($total <= 0) {
            return [];
        }

        $shares = [];
        $assigned = 0.0;

        foreach ($picks->values() as $index => $pick) {
            // The last share takes the remainder, so rounding can never leave the lot
            // rows summing to something other than the material total.
            $share = $index === $picks->count() - 1
                ? round($delta - $assigned, 4)
                : round($delta * ((float) $pick->picked_qty / $total), 4);

            $assigned += $share;
            $lotId = (int) $pick->material_lot_id;
            $shares[$lotId] = round(($shares[$lotId] ?? 0) + $share, 4);
        }

        return $shares;
    }

    /**
     * Refuse a deduction the location cannot cover, when the plant says so.
     *
     * When it does not, the deduction goes through and the balance is allowed below
     * zero — the movement's reason records the shortfall so it can be investigated
     * without production having been stopped.
     *
     * @throws \DomainException
     */
    private function assertSufficient(
        MaterialAllocation $allocation,
        Warehouse $warehouse,
        float $delta,
        float $available,
    ): void {
        if ($delta <= 0 || ! $this->warehouseStock->blocksNegativeStock()) {
            return;
        }

        if ($available + 0.00005 < $delta) {
            throw new \DomainException(__(
                'Location :warehouse does not hold enough :material to consume :needed (:available available).',
                [
                    'warehouse' => $warehouse->code,
                    'material' => $allocation->material?->code ?? $allocation->material_id,
                    'needed' => $delta,
                    'available' => $available,
                ],
            ));
        }
    }

    /**
     * Ledger text for the deduction.
     *
     * When the plant does not block negative balances, an overdraw still has to be
     * findable afterwards — so the shortfall is spelled out in the movement rather
     * than left to be inferred from a balance that is now negative for other reasons
     * too. `$available` is the balance from before the move.
     */
    private function reason(MaterialAllocation $allocation, float $delta, float $available): string
    {
        $base = $delta > 0
            ? 'Consumed on batch #'.$allocation->batch_id
            : 'Consumption corrected down on batch #'.$allocation->batch_id;

        if ($delta > 0 && $available + 0.00005 < $delta) {
            return $base.' — SHORTFALL: location held '.round($available, 4).' of '.round($delta, 4);
        }

        return $base;
    }
}
