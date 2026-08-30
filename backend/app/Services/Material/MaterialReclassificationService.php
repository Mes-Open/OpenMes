<?php

namespace App\Services\Material;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialReclassification;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Quality\MaterialHoldService;
use Illuminate\Support\Facades\DB;

/**
 * Material reclassification (#99): move a quantity between material classes
 * (a regrade), or change a lot's status. Both produce an append-only
 * MaterialReclassification audit row; class moves also book a pair of correlated
 * stock movements, and a reject books a scrap movement. Every stock delta routes
 * through StockMovementService so the ledger stays the single source of truth.
 */
class MaterialReclassificationService
{
    public function __construct(
        protected StockMovementService $stockMovements,
        protected MaterialHoldService $holds,
    ) {}

    /**
     * Move a quantity from one material (class) to another — e.g. regrade a batch
     * of raw stock down a grade. Books TYPE_RECLASSIFY on both the source (−) and
     * the target (+), correlated by the audit row's id. If a source lot is given,
     * its available quantity is consumed too.
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function reclassifyClass(
        Material $source,
        Material $target,
        float $qty,
        User $by,
        ?MaterialLot $sourceLot = null,
        ?string $reason = null,
    ): MaterialReclassification {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Reclassification quantity must be positive.');
        }
        if ($source->id === $target->id) {
            throw new \DomainException('Source and target material must differ.');
        }
        if ($sourceLot) {
            if ($sourceLot->material_id !== $source->id) {
                throw new \DomainException('The lot does not belong to the source material.');
            }
            if ($qty > (float) $sourceLot->quantity_available + 1e-9) {
                throw new \InvalidArgumentException('Reclassification quantity exceeds the lot available quantity.');
            }
        }

        return DB::transaction(function () use ($source, $target, $qty, $by, $sourceLot, $reason) {
            $record = MaterialReclassification::create([
                'type' => MaterialReclassification::TYPE_CLASS,
                'source_material_id' => $source->id,
                'target_material_id' => $target->id,
                'source_lot_id' => $sourceLot?->id,
                'quantity' => $qty,
                'reason' => $reason,
                'performed_by' => $by->id,
                'performed_at' => now(),
            ]);

            $note = $reason ?? 'Reclassified '.$source->code.' → '.$target->code;

            $this->stockMovements->record(
                $source,
                StockMovement::TYPE_RECLASSIFY,
                -$qty,
                user: $by,
                sourceType: StockMovement::SOURCE_RECLASSIFICATION,
                sourceId: $record->id,
                reason: $note,
            );
            $this->stockMovements->record(
                $target,
                StockMovement::TYPE_RECLASSIFY,
                $qty,
                user: $by,
                sourceType: StockMovement::SOURCE_RECLASSIFICATION,
                sourceId: $record->id,
                reason: $note,
            );

            if ($sourceLot) {
                $sourceLot->consume($qty); // decrements available; flips to CONSUMED when empty
            }

            return $record->fresh();
        });
    }

    /**
     * Change a lot's status. released↔quarantine reuse the manual hold/release
     * path (no stock delta). A move to `rejected` scraps the lot's remaining
     * available quantity out of stock and zeroes it.
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function reclassifyStatus(
        MaterialLot $lot,
        string $toStatus,
        User $by,
        ?string $reason = null,
    ): MaterialReclassification {
        if (! in_array($toStatus, [MaterialLot::STATUS_RELEASED, MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED], true)) {
            throw new \InvalidArgumentException("Unsupported target status: {$toStatus}.");
        }

        return DB::transaction(function () use ($lot, $toStatus, $by, $reason) {
            // Lock and re-read so two concurrent rejects can't both scrap the same
            // available quantity and double-decrement stock.
            $lot = MaterialLot::query()->lockForUpdate()->findOrFail($lot->getKey());
            $fromStatus = $lot->status;

            // Create the audit row first so the reject scrap movement can point at it.
            $record = MaterialReclassification::create([
                'type' => MaterialReclassification::TYPE_STATUS,
                'source_material_id' => $lot->material_id,
                'source_lot_id' => $lot->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'performed_by' => $by->id,
                'performed_at' => now(),
            ]);

            switch ($toStatus) {
                case MaterialLot::STATUS_QUARANTINE:
                    $this->holds->hold($lot, $reason ?? '', $by);
                    break;

                case MaterialLot::STATUS_RELEASED:
                    $this->holds->release($lot, $by);
                    break;

                case MaterialLot::STATUS_REJECTED:
                    if (in_array($lot->status, [MaterialLot::STATUS_CONSUMED, MaterialLot::STATUS_REJECTED], true)) {
                        throw new \DomainException("Cannot reject a {$lot->status} lot.");
                    }
                    $remaining = (float) $lot->quantity_available;
                    if ($remaining > 0 && $lot->material) {
                        $this->stockMovements->record(
                            $lot->material,
                            StockMovement::TYPE_SCRAP,
                            -$remaining,
                            user: $by,
                            sourceType: StockMovement::SOURCE_RECLASSIFICATION,
                            sourceId: $record->id,
                            reason: $reason ?? 'Lot '.$lot->lot_number.' rejected — scrapped from stock',
                        );
                    }
                    $lot->update([
                        'status' => MaterialLot::STATUS_REJECTED,
                        'quantity_available' => 0,
                    ]);
                    break;
            }

            return $record->fresh();
        });
    }
}
