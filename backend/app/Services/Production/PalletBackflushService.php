<?php

namespace App\Services\Production;

use App\Models\Material;
use App\Models\Pallet;
use App\Models\StockMovement;
use App\Models\User;
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

    public function __construct(private readonly StockMovementService $stockMovements) {}

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
     * case).
     *
     * @return \Illuminate\Support\Collection<int, StockMovement>
     */
    public function backflush(Pallet $pallet, float $quantity, ?User $user = null): Collection
    {
        if ($quantity <= 0) {
            return collect();
        }

        $bom = $this->resolveBom($pallet);
        if (empty($bom)) {
            return collect();
        }

        return DB::transaction(function () use ($pallet, $quantity, $user, $bom) {
            $movements = collect();

            foreach ($bom as $item) {
                $materialId = $item['material_id'] ?? null;
                if (! $materialId) {
                    continue;
                }

                $required = $this->requiredQty($item, $quantity);
                if ($required <= 0) {
                    continue;
                }

                $material = Material::find($materialId);
                if (! $material) {
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

    /** The BOM (snapshot) backing this pallet's work order. */
    private function resolveBom(Pallet $pallet): array
    {
        $pallet->loadMissing('workOrder');
        $snapshot = $pallet->workOrder?->process_snapshot ?? [];

        return $snapshot['bom'] ?? [];
    }

    /** Required component qty for the produced quantity: per-unit + scrap%. */
    private function requiredQty(array $item, float $quantity): float
    {
        $base = ((float) ($item['quantity_per_unit'] ?? 0)) * $quantity;
        $scrap = $base * (((float) ($item['scrap_percentage'] ?? 0)) / 100);

        return round($base + $scrap, 4);
    }
}
