<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\Material;
use App\Models\Pallet;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Material\BomService;
use App\Services\Material\StockMovementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Milestone (backflush) consumption: when a pallet is created, declare the
 * component consumption implied by the produced quantity (via the work order's
 * BOM) and deduct it from stock in one shot - rather than booking consumption
 * continuously through the step/allocation flow.
 *
 * It is gated by the `backflush_on_pallet_creation` system setting (off by
 * default) so it never changes existing behaviour unless explicitly enabled,
 * and it is independent of the allocation engine: each consumption is a plain
 * StockMovement (type=consume) linked to the pallet via source_type/source_id.
 */
class PalletBackflushService
{
    /** System-settings key for the configurable milestone trigger. */
    public const SETTING_KEY = 'backflush_on_pallet_creation';

    public function __construct(
        private readonly StockMovementService $stockMovements,
        private readonly BomService $bomService,
    ) {}

    /** Whether milestone backflush on pallet creation is enabled. */
    public function isEnabled(): bool
    {
        try {
            $row = DB::table('system_settings')->where('key', self::SETTING_KEY)->value('value');

            return (bool) json_decode($row ?? 'false', true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Entry point from pallet creation. With an explicit produced quantity, book
     * that pallet's consumption. Without one (the packaging station sends none,
     * and a fresh pallet has qty 0), book the batch's BOM exactly once - at the
     * first pallet milestone for the batch - so a batch split across several
     * pallets is not consumed multiple times.
     *
     * @return \Illuminate\Support\Collection<int, StockMovement>
     */
    public function backflushForPallet(Pallet $pallet, ?float $explicitQty = null, ?User $user = null): Collection
    {
        if ($explicitQty !== null && $explicitQty > 0) {
            return $this->backflush($pallet, $explicitQty, $user);
        }

        $pallet->loadMissing('batch');
        $batch = $pallet->batch;
        if (! $batch) {
            return collect();
        }

        // Lock the batch row so two concurrent pallet creates can't both pass the
        // once-per-batch guard and double-deduct stock.
        return DB::transaction(function () use ($pallet, $batch, $user) {
            Batch::withTrashed()->whereKey($batch->id)->lockForUpdate()->first();

            if ($this->batchAlreadyBackflushed($batch, $pallet)) {
                return collect();
            }

            return $this->backflush($pallet, $this->resolveQuantity($pallet, null), $user);
        });
    }

    /**
     * The quantity to backflush against: the explicit produced quantity supplied
     * at pallet creation, otherwise the pallet's batch produced (then target)
     * quantity. Zero when nothing is resolvable - nothing is then consumed.
     */
    public function resolveQuantity(Pallet $pallet, ?float $explicit = null): float
    {
        if ($explicit !== null && $explicit > 0) {
            return $explicit;
        }

        $pallet->loadMissing('batch');
        $batch = $pallet->batch;
        if ($batch) {
            if ((float) $batch->produced_qty > 0) {
                return (float) $batch->produced_qty;
            }
            if ((float) $batch->target_qty > 0) {
                return (float) $batch->target_qty;
            }
        }

        return 0.0;
    }

    /**
     * Declare and book the BOM consumption implied by $quantity for $pallet:
     * one consume StockMovement per BOM component, deducting from stock and
     * linked to the pallet. Returns the booked movements (empty when there is
     * no BOM or the quantity is zero - the "pallet with no BOM consumption"
     * case). The per-unit/scrap explosion is delegated to BomService so the
     * formula stays single-sourced.
     *
     * @return \Illuminate\Support\Collection<int, StockMovement>
     */
    public function backflush(Pallet $pallet, float $quantity, ?User $user = null): Collection
    {
        if ($quantity <= 0) {
            return collect();
        }

        $pallet->loadMissing('workOrder');
        $rows = $this->bomService->calculateFromSnapshot($pallet->workOrder?->process_snapshot ?? [], $quantity);
        $materialIds = collect($rows)->pluck('material_id')->filter()->unique();
        if ($materialIds->isEmpty()) {
            return collect();
        }

        // Preload the materials in one query (no find()-in-loop).
        $materials = Material::whereIn('id', $materialIds)->get()->keyBy('id');

        return DB::transaction(function () use ($rows, $materials, $pallet, $user) {
            $movements = collect();

            foreach ($rows as $item) {
                $required = (float) ($item['required_qty'] ?? 0);
                $material = $materials->get($item['material_id'] ?? null);
                if (! $material || $required <= 0) {
                    continue;
                }

                $movements->push($this->stockMovements->record(
                    $material,
                    StockMovement::TYPE_CONSUME,
                    -$required,
                    $user,
                    StockMovement::SOURCE_PALLET,
                    $pallet->id,
                    'Backflush on pallet '.$pallet->pallet_no,
                ));
            }

            return $movements;
        });
    }

    /**
     * Has any other pallet of this batch already booked a backflush consumption?
     * Includes soft-deleted pallets: their consumption movements survive the
     * delete, so a later pallet must not rebook the batch just because an earlier
     * one was removed.
     */
    private function batchAlreadyBackflushed(Batch $batch, Pallet $current): bool
    {
        return StockMovement::where('source_type', StockMovement::SOURCE_PALLET)
            ->whereIn('source_id', $batch->pallets()->withTrashed()->whereKeyNot($current->id)->pluck('id'))
            ->exists();
    }
}
