<?php

namespace App\Services\Logistics;

use App\Models\Pallet;
use App\Models\PalletMovement;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;

/**
 * Single point of entry for recording a physical pallet movement (#103).
 *
 * Atomically snapshots the pallet's current location as the move's origin,
 * updates the pallet to its new location, and appends an immutable
 * pallet_movements row crediting the logistics operator who performed it.
 */
class PalletMovementService
{
    public function record(
        Pallet $pallet,
        Worker $operator,
        string $toLocation,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): PalletMovement {
        return DB::transaction(function () use ($pallet, $operator, $toLocation, $recordedBy, $notes) {
            // Lock + re-read so the from_location we capture is the real current
            // location even under concurrent moves of the same pallet.
            $locked = Pallet::whereKey($pallet->getKey())->lockForUpdate()->firstOrFail();

            $fromLocation = $locked->location;
            $locked->update(['location' => $toLocation]);

            return PalletMovement::create([
                'pallet_id' => $locked->id,
                'worker_id' => $operator->id,
                'from_location' => $fromLocation,
                'to_location' => $toLocation,
                'moved_at' => now(),
                'notes' => $notes,
                'performed_by' => $recordedBy?->id,
            ]);
        });
    }
}
