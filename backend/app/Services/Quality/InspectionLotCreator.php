<?php

namespace App\Services\Quality;

use App\Models\Inspection;
use App\Models\MaterialLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns an inspected delivery into a MaterialLot so production can pick from
 * it via FEFO/FIFO, or so a held lot is on record. Opt-in through the
 * `lot_tracking_enabled` setting.
 *
 * Only a user allowed to decide on the lot (InspectionPolicy::disposition)
 * reaches this: on completing an inspection they hold that right for, or when
 * they apply a disposition to one an inspector completed.
 */
class InspectionLotCreator
{
    /**
     * Create the lot in the given status unless tracking is off or the
     * inspection already has one. A released lot has its whole received
     * quantity available; any other status has none.
     *
     * $createdBy is whoever puts the lot on record — the person deciding on
     * it; the inspector when not given.
     */
    public function ensureFromInspection(Inspection $inspection, string $lotStatus, ?User $createdBy = null): ?MaterialLot
    {
        if (! $this->trackingEnabled()) {
            return null;
        }

        // Avoid duplicating a lot if the inspection is processed twice for any
        // reason. Callers hold a lock on the inspection row, so two requests
        // cannot both pass this check.
        if (MaterialLot::where('inspection_id', $inspection->id)->exists()) {
            return null;
        }

        $inspection->loadMissing('material');
        $qty = $inspection->quantity_received ?? 0;

        return MaterialLot::create([
            'tenant_id' => $inspection->tenant_id,
            'material_id' => $inspection->material_id,
            'lot_number' => $inspection->lot_number,
            'unit_of_measure' => $inspection->material?->unit_of_measure ?? 'pcs',
            'supplier_lot_no' => $inspection->supplier_lot_ref,
            'source_container_no' => $inspection->source_container_no,
            'quantity_received' => $qty,
            'quantity_available' => $lotStatus === MaterialLot::STATUS_RELEASED ? $qty : 0,
            'received_at' => $inspection->started_at ?? now(),
            'inspection_id' => $inspection->id,
            'created_by_id' => $createdBy?->id ?? $inspection->inspector_id,
            'status' => $lotStatus,
        ]);
    }

    private function trackingEnabled(): bool
    {
        try {
            return (bool) json_decode(
                DB::table('system_settings')->where('key', 'lot_tracking_enabled')->value('value') ?? 'false',
                true,
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
