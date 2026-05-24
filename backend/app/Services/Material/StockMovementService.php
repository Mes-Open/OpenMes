<?php

namespace App\Services\Material;

use App\Models\Material;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single point of entry for any change to materials.stock_quantity.
 * Every callsite (allocation, consume, return, receipt, adjustment)
 * routes through here so the stock_movements ledger is the authoritative
 * audit trail and stock_quantity drift can be reconciled later.
 *
 * Use within an existing DB::transaction whenever possible — the method
 * locks the material row and re-reads it post-update to capture the
 * canonical balance_after value.
 */
class StockMovementService
{
    public function record(
        Material $material,
        string $movementType,
        float $signedQuantity,
        ?User $user = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $reason = null,
    ): StockMovement {
        return DB::transaction(function () use ($material, $movementType, $signedQuantity, $user, $sourceType, $sourceId, $reason) {
            // Lock + re-read so the balance_after we record is the real
            // post-mutation value, even under concurrency.
            $locked = Material::where('id', $material->id)->lockForUpdate()->first();

            if ($signedQuantity >= 0) {
                $locked->increment('stock_quantity', $signedQuantity);
            } else {
                $locked->decrement('stock_quantity', abs($signedQuantity));
            }

            $locked->refresh();

            return StockMovement::create([
                'material_id' => $locked->id,
                'movement_type' => $movementType,
                'quantity' => $signedQuantity,
                'balance_after' => $locked->stock_quantity,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reason' => $reason,
                'performed_by' => $user?->id,
                'performed_at' => now(),
            ]);
        });
    }
}
