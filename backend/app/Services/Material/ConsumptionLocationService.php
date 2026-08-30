<?php

namespace App\Services\Material;

use App\Models\MaterialAllocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseStockService;
use App\Support\ModuleRegistry;
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
     * Book consumption for an allocation against its location(s), moving the balance
     * by the difference between what is now consumed and what was already deducted.
     *
     * Called every time a consumed quantity is written — an operator's entry, a
     * correction, batch completion — so it must be the delta, not the total. A
     * downward correction produces a negative delta and credits the location back.
     *
     * Returns the movements it wrote, one per location touched, and an empty array
     * when there was nothing to move (no location resolvable, the Warehouses module
     * is off, or the quantity did not change).
     *
     * @return array<int, StockMovement>
     *
     * @throws \DomainException When a location lacks the stock and the plant blocks negative balances.
     */
    public function deduct(MaterialAllocation $allocation, float $consumedTotal, ?User $user = null): array
    {
        // Per-location balances belong to the Warehouses module (#212). With it off,
        // a plant that once had warehouses must not have production refused — or
        // silently booked — against balances nobody is maintaining any more.
        if (! ModuleRegistry::isModuleEnabled('warehouse')) {
            return [];
        }

        return DB::transaction(function () use ($allocation, $consumedTotal, $user) {
            // Re-read under a lock: `location_deducted_qty` is a read-modify-write, and
            // two operators booking on the same allocation would otherwise each deduct
            // against the same stale starting point.
            $locked = MaterialAllocation::where('id', $allocation->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return [];
            }

            $fallback = $this->resolveWarehouse($locked);

            if ($fallback === null) {
                // No location to attribute this to — a plant that does not track stock
                // per location. Global stock and the lot already moved at allocation,
                // so there is simply nothing further to do.
                return [];
            }

            // Quantise to the precision `warehouse_stocks.quantity` actually stores
            // (decimal(14,3)). Booking a 4-decimal quantity against a 3-decimal column
            // would leave the allocation's running total and the balance disagreeing by
            // the rounding, and the residue would be re-deducted on every later call.
            $delta = round(round($consumedTotal, 3) - (float) $locked->location_deducted_qty, 3);

            if (abs($delta) < 0.0005) {
                return [];
            }

            $movements = [];

            foreach ($this->splitByLocation($locked, $delta, $fallback) as $warehouseId => $split) {
                $movement = $this->applyAtLocation($locked, (int) $warehouseId, $split, $user);

                if ($movement) {
                    $movements[] = $movement;
                }
            }

            $locked->update([
                // The allocation's own location: the one resolved for it, kept for the
                // lot-less path and frozen so a correction credits back the same store.
                'consumption_warehouse_id' => $locked->consumption_warehouse_id ?? $fallback->id,
                'location_deducted_qty' => round((float) $locked->location_deducted_qty + $delta, 3),
            ]);

            return $movements;
        });
    }

    /**
     * Move one location's share of a deduction and write its ledger row.
     *
     * @param  array{total: float, lots: array<int, float>}  $split
     */
    private function applyAtLocation(
        MaterialAllocation $allocation,
        int $warehouseId,
        array $split,
        ?User $user,
    ): ?StockMovement {
        $delta = $split['total'];

        if (abs($delta) < 0.0005) {
            return null;
        }

        $keys = [
            'warehouse_id' => $warehouseId,
            'material_id' => $allocation->material_id,
        ];

        // Lock the material total for this location before reading it: the balance
        // decides both whether the deduction is refused and how big a shortfall to
        // flag, and an unlocked read would let two bookings pass the same check and
        // overdraw together. The lock is held for the rest of this transaction, so the
        // adjust() below re-locks nothing.
        $balance = $this->warehouseStock->lockOrCreate([...$keys, 'material_lot_id' => null]);
        $available = (float) $balance->quantity;

        $warehouse = Warehouse::find($warehouseId);

        if ($warehouse === null) {
            return null;
        }

        $this->assertSufficient($allocation, $warehouse, $delta, $available);

        // The lot-level balance and the material total for that location are kept
        // separately (#212), so a per-material view does not have to sum lots.
        foreach ($split['lots'] as $lotId => $lotDelta) {
            $this->warehouseStock->adjust([...$keys, 'material_lot_id' => $lotId], -$lotDelta);
        }

        $this->warehouseStock->adjust([...$keys, 'material_lot_id' => null], -$delta);

        // Audit: one ledger row per location per deduction, carrying the warehouse.
        // `adjustGlobal` is off because allocation already moved the plant-wide
        // quantity — this row exists to say which location gave the material up.
        return $this->stockMovements->record(
            material: $allocation->material,
            movementType: $delta > 0 ? StockMovement::TYPE_CONSUME : StockMovement::TYPE_RETURN,
            signedQuantity: -$delta,
            user: $user,
            sourceType: $allocation->batch_step_id
                ? StockMovement::SOURCE_BATCH_STEP
                : StockMovement::SOURCE_BATCH,
            sourceId: $allocation->batch_step_id ?: $allocation->batch_id,
            reason: $this->reason($allocation, $delta, $available),
            warehouseId: $warehouseId,
            adjustGlobal: false,
        );
    }

    /**
     * Give back everything this allocation took off its location(s) — used when a
     * batch is cancelled after consumption had already been booked.
     *
     * @return array<int, StockMovement>
     */
    public function reverse(MaterialAllocation $allocation, ?User $user = null): array
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
     * Split a deduction across the locations it actually comes off.
     *
     * Lot picks are not guaranteed to sit in one store — lot selection is FEFO across
     * the material's lots, so a single allocation can legitimately draw from two.
     * Each pick's share therefore goes to its own lot's warehouse (falling back to the
     * allocation's location for a lot that names none), proportionally to what was
     * picked from it, so no store is ever charged for material another one gave up.
     * With nothing picked by lot, the whole delta goes to the allocation's location.
     *
     * The shares are proportional to `picked_qty`, which does not change after the
     * fact — so a later correction splits exactly the way the deduction did and every
     * store is credited back precisely what it gave.
     *
     * @return array<int, array{total: float, lots: array<int, float>}>
     */
    private function splitByLocation(MaterialAllocation $allocation, float $delta, Warehouse $fallback): array
    {
        $picks = $allocation->lotPicks->filter(fn ($pick) => (float) $pick->picked_qty > 0);
        $total = (float) $picks->sum('picked_qty');

        if ($total <= 0) {
            return [$fallback->id => ['total' => $delta, 'lots' => []]];
        }

        $split = [];
        $assigned = 0.0;

        foreach ($picks->values() as $index => $pick) {
            // The last share takes the remainder, so rounding can never leave the
            // per-location rows summing to something other than the delta.
            $share = $index === $picks->count() - 1
                ? round($delta - $assigned, 3)
                : round($delta * ((float) $pick->picked_qty / $total), 3);

            $assigned = round($assigned + $share, 3);

            if (abs($share) < 0.0005) {
                continue;
            }

            $warehouseId = (int) ($pick->lot?->warehouse_id ?: $fallback->id);
            $lotId = (int) $pick->material_lot_id;

            $split[$warehouseId]['total'] = round(($split[$warehouseId]['total'] ?? 0) + $share, 3);
            $split[$warehouseId]['lots'][$lotId] = round(($split[$warehouseId]['lots'][$lotId] ?? 0) + $share, 3);
        }

        return $split;
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
