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
    /**
     * Pick lots for the given allocation/material/quantity. Decrements
     * each picked lot's available_qty, marks depleted lots, and writes
     * allocation_lot_picks rows. Returns the picks collection.
     *
     * @throws InsufficientStockException when total available across lots
     *         is less than required.
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

            $totalAvailable = (float) $candidates->sum(fn ($l) => $l->available_qty);
            if ($totalAvailable < $requiredQty) {
                throw new InsufficientStockException($material, $requiredQty, $totalAvailable);
            }

            $remaining = $requiredQty;
            $picks = [];

            foreach ($candidates as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $lot->available_qty);

                $picks[] = AllocationLotPick::create([
                    'tenant_id' => $allocation->tenant_id,
                    'material_allocation_id' => $allocation->id,
                    'material_lot_id' => $lot->id,
                    'picked_qty' => $take,
                    'picking_strategy' => $strategy,
                ]);

                $lot->decrement('available_qty', $take);
                $lot->refresh()->markDepletedIfEmpty();

                $remaining -= $take;
            }

            return $picks;
        });
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
                $pick->lot->increment('available_qty', (float) $pick->picked_qty);
                if ($pick->lot->status === MaterialLot::STATUS_DEPLETED && (float) $pick->lot->fresh()->available_qty > 0) {
                    $pick->lot->update(['status' => MaterialLot::STATUS_AVAILABLE]);
                }
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
     * @return \Illuminate\Support\Collection<int, MaterialLot>
     */
    private function orderedAvailableLots(int $materialId, string $strategy): \Illuminate\Support\Collection
    {
        $q = MaterialLot::where('material_id', $materialId)
            ->where('status', MaterialLot::STATUS_AVAILABLE)
            ->where('available_qty', '>', 0)
            ->lockForUpdate();

        return match ($strategy) {
            'fifo' => $q->orderBy('received_at')->orderBy('id')->get(),
            'lifo' => $q->orderByDesc('received_at')->orderByDesc('id')->get(),
            'manual' => collect(), // caller chooses
            // default FEFO: nulls last (no expiry → use later)
            default => $q->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('received_at')->get(),
        };
    }
}
