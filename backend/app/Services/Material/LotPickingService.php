<?php

namespace App\Services\Material;

use App\Exceptions\InsufficientStockException;
use App\Models\AllocationLotPick;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use Illuminate\Support\Facades\DB;

/**
 * Picks lots to satisfy an allocation. The picking strategy decides the
 * order in which available lots are considered:
 *
 *   FEFO — first expiring first out (default; right answer for food/medical)
 *   FIFO — oldest received first (right answer for non-perishables)
 *   LIFO — newest received first (rare, for some accounting scenarios)
 *   MANUAL — caller supplies lot ids + quantities
 */
class LotPickingService
{
    /** Floating-point tolerance for quantity comparisons (matches test deltas). */
    private const EPSILON = 0.0001;

    /**
     * Pick lots for the given allocation/material/quantity. Decrements
     * each picked lot's available_qty, marks depleted lots, and writes
     * allocation_lot_picks rows. Returns the picks collection.
     *
     * @throws InsufficientStockException when total available across lots
     *                                    is less than required.
     */
    public function pickForAllocation(
        MaterialAllocation $allocation,
        Material $material,
        float $requiredQty,
        ?string $strategy = null,
    ): array {
        $strategy = $strategy ?? $this->defaultStrategy();

        return DB::transaction(function () use ($allocation, $material, $requiredQty, $strategy) {
            $candidates = $this->orderedAvailableLots($material->id, $strategy);

            $totalAvailable = (float) $candidates->sum(fn ($l) => $l->quantity_available);
            if ($totalAvailable < $requiredQty) {
                throw new InsufficientStockException($material, $requiredQty, $totalAvailable);
            }

            $remaining = $requiredQty;
            $picks = [];

            foreach ($candidates as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $lot->quantity_available);

                $picks[] = AllocationLotPick::create([
                    'tenant_id' => $allocation->tenant_id,
                    'material_allocation_id' => $allocation->id,
                    'material_lot_id' => $lot->id,
                    'picked_qty' => $take,
                    'picking_strategy' => $strategy,
                ]);

                $lot->decrement('quantity_available', $take);
                $lot->refresh()->markConsumedIfEmpty();
                \App\Sync\CollectionBroadcaster::flush($lot); // decrement bypasses model events

                $remaining -= $take;
            }

            return $picks;
        });
    }

    /**
     * Pick the exact lots + quantities the operator chose at WO time
     * (ERP-aligned "suggest + override"). Validates that each lot belongs to
     * the material, is released/available, the per-lot quantity fits, and the
     * chosen quantities sum to the required amount. Decrements each lot and
     * writes allocation_lot_picks rows with the MANUAL strategy.
     *
     * @param  array<int, array{material_lot_id: int|string, picked_qty: int|float|string}>  $chosen
     * @return array<int, AllocationLotPick>
     *
     * @throws \DomainException when the chosen lots/quantities are invalid
     * @throws InsufficientStockException when a lot can't cover its line
     */
    public function pickManualForAllocation(
        MaterialAllocation $allocation,
        Material $material,
        float $requiredQty,
        array $chosen,
    ): array {
        return DB::transaction(function () use ($allocation, $material, $requiredQty, $chosen) {
            // Normalise + collapse duplicate lot lines (the unique index on
            // (allocation, lot) forbids two rows for the same lot anyway).
            $lines = [];
            foreach ($chosen as $row) {
                $lotId = (int) ($row['material_lot_id'] ?? 0);
                $qty = round((float) ($row['picked_qty'] ?? 0), 4);
                if ($lotId <= 0 || $qty <= 0) {
                    throw new \DomainException(__('Each lot pick must reference a lot and a positive quantity.'));
                }
                $lines[$lotId] = ($lines[$lotId] ?? 0) + $qty;
            }

            if (empty($lines)) {
                throw new \DomainException(__('At least one lot must be picked.'));
            }

            if (abs(array_sum($lines) - $requiredQty) > self::EPSILON) {
                throw new \DomainException(__('Quantities must sum to the required amount'));
            }

            // Lock the chosen lots, scoped to this material + released status.
            $lots = MaterialLot::whereIn('id', array_keys($lines))
                ->where('material_id', $material->id)
                ->where('status', MaterialLot::STATUS_RELEASED)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $picks = [];
            foreach ($lines as $lotId => $qty) {
                $lot = $lots->get($lotId);
                if (! $lot) {
                    throw new \DomainException(__('No lots available for this material'));
                }
                if ($qty > (float) $lot->quantity_available + self::EPSILON) {
                    throw new InsufficientStockException($material, $qty, (float) $lot->quantity_available);
                }

                $picks[] = AllocationLotPick::create([
                    'tenant_id' => $allocation->tenant_id,
                    'material_allocation_id' => $allocation->id,
                    'material_lot_id' => $lot->id,
                    'picked_qty' => $qty,
                    'picking_strategy' => AllocationLotPick::STRATEGY_MANUAL,
                ]);

                $lot->decrement('quantity_available', $qty);
                $lot->refresh()->markConsumedIfEmpty();
                \App\Sync\CollectionBroadcaster::flush($lot); // decrement bypasses model events
            }

            return $picks;
        });
    }

    /**
     * Read-only proposal for the WO-time picking UI: the strategy that would
     * run, the proposed (lot, qty) split for the required quantity, and the
     * full candidate lot list the operator can pick from. Performs no locking
     * and no mutations (locking outside a transaction is a no-op on Postgres).
     *
     * @return array{strategy: string, proposed: array<int, array{material_lot_id: int, picked_qty: float}>, candidates: array<int, array{id: int, lot_number: string, quantity_available: float, expiry_date: ?string, received_at: ?string, status: string}>}
     */
    public function proposePicks(Material $material, float $requiredQty, ?string $strategy = null): array
    {
        $strategy = $strategy ?? $this->defaultStrategy();

        // Manual strategy proposes nothing; still order candidates by FEFO so
        // the operator sees the most sensible lots first.
        $orderStrategy = $strategy === 'manual' ? 'fefo' : $strategy;
        $lots = $this->applyStrategyOrder($this->availableLotsQuery($material->id), $orderStrategy)->get();

        $proposed = [];
        if ($strategy !== 'manual') {
            $remaining = $requiredQty;
            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min($remaining, (float) $lot->quantity_available);
                $proposed[] = ['material_lot_id' => $lot->id, 'picked_qty' => round($take, 4)];
                $remaining -= $take;
            }
        }

        return [
            'strategy' => $strategy,
            'proposed' => $proposed,
            'candidates' => $lots->map(fn ($lot) => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'quantity_available' => (float) $lot->quantity_available,
                'expiry_date' => $lot->expiry_date?->toDateString(),
                'received_at' => $lot->received_at?->toDateString(),
                'status' => $lot->status,
            ])->values()->all(),
        ];
    }

    /**
     * Return lots to stock when an allocation is cancelled. Re-opens
     * depleted lots back to available status.
     */
    public function returnPicksForAllocation(MaterialAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            $picks = $allocation->lotPicks()->with('lot')->lockForUpdate()->get();

            foreach ($picks as $pick) {
                if (! $pick->lot) {
                    continue;
                }
                $pick->lot->increment('quantity_available', (float) $pick->picked_qty);
                if ($pick->lot->status === MaterialLot::STATUS_CONSUMED && (float) $pick->lot->fresh()->quantity_available > 0) {
                    $pick->lot->update(['status' => MaterialLot::STATUS_RELEASED]);
                }
                \App\Sync\CollectionBroadcaster::flush($pick->lot); // increment bypasses model events
            }

            $allocation->lotPicks()->delete();
        });
    }

    public function isLotTrackingEnabled(): bool
    {
        try {
            $row = DB::table('system_settings')->where('key', 'lot_tracking_enabled')->value('value');

            return (bool) json_decode($row ?? 'false', true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function defaultStrategy(): string
    {
        try {
            $row = DB::table('system_settings')->where('key', 'lot_picking_strategy')->value('value');
            $val = json_decode($row ?? '"fefo"', true);

            return in_array($val, ['fefo', 'fifo', 'lifo', 'manual'], true) ? $val : 'fefo';
        } catch (\Throwable) {
            return 'fefo';
        }
    }

    /**
     * Locked, strategy-ordered available lots for the auto-pick path.
     * 'manual' returns empty - the caller supplies lots via pickManualForAllocation().
     *
     * @return \Illuminate\Support\Collection<int, MaterialLot>
     */
    private function orderedAvailableLots(int $materialId, string $strategy): \Illuminate\Support\Collection
    {
        if ($strategy === 'manual') {
            return collect(); // caller chooses
        }

        return $this->applyStrategyOrder(
            $this->availableLotsQuery($materialId)->lockForUpdate(),
            $strategy,
        )->get();
    }

    /** Base query for released lots with stock on hand (no lock, no ordering). */
    private function availableLotsQuery(int $materialId): \Illuminate\Database\Eloquent\Builder
    {
        return MaterialLot::where('material_id', $materialId)
            ->where('status', MaterialLot::STATUS_RELEASED)
            ->where('quantity_available', '>', 0);
    }

    /**
     * Apply the picking-strategy ordering to a lot query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MaterialLot>  $q
     * @return \Illuminate\Database\Eloquent\Builder<MaterialLot>
     */
    private function applyStrategyOrder(\Illuminate\Database\Eloquent\Builder $q, string $strategy): \Illuminate\Database\Eloquent\Builder
    {
        return match ($strategy) {
            'fifo' => $q->orderBy('received_at')->orderBy('id'),
            'lifo' => $q->orderByDesc('received_at')->orderByDesc('id'),
            // default FEFO: nulls last (no expiry → use later)
            default => $q->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('received_at'),
        };
    }
}
