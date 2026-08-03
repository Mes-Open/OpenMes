<?php

namespace App\Services\Logistics;

use App\Models\Pallet;
use App\Models\PalletMovement;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;

/**
 * Single point of entry for changing where a pallet is and where it is going
 * (#103, #101).
 *
 * Every mutation is atomic (the pallet row is locked so the "from" snapshot is
 * the real current state under concurrent terminals) and appends an immutable
 * pallet_movements row, so location and destination share one auditable
 * timeline rather than two.
 */
class PalletMovementService
{
    /**
     * Record a physical move.
     *
     * $toDestination re-routes the pallet as part of the same event; passing
     * null leaves the standing destination in place. Landing on the destination
     * clears it and stamps the arrival — use assignDestination() to explicitly
     * unassign one.
     */
    public function record(
        Pallet $pallet,
        Worker $operator,
        string $toLocation,
        ?User $recordedBy = null,
        ?string $notes = null,
        ?string $toDestination = null,
    ): PalletMovement {
        return DB::transaction(function () use ($pallet, $operator, $toLocation, $recordedBy, $notes, $toDestination) {
            // Lock + re-read so the from_location we capture is the real current
            // location even under concurrent moves of the same pallet.
            $locked = Pallet::whereKey($pallet->getKey())->lockForUpdate()->firstOrFail();

            $fromLocation = $locked->location;
            $fromDestination = $locked->destination;

            $destination = $toDestination ?? $fromDestination;
            $arrivedAt = $locked->arrived_at;

            if ($destination !== null && $destination === $toLocation) {
                // Reached its target: the destination is satisfied, not pending.
                $destination = null;
                $arrivedAt = now();
            } elseif ($toLocation !== $fromLocation || $destination !== $fromDestination) {
                // Moved on, or re-routed somewhere new — any earlier arrival no
                // longer describes where the pallet stands.
                $arrivedAt = null;
            }

            $locked->update([
                'location' => $toLocation,
                'destination' => $destination,
                'arrived_at' => $arrivedAt,
            ]);

            return $this->append(
                $locked, $operator, $fromLocation, $toLocation,
                $fromDestination, $destination, $recordedBy, $notes,
            );
        });
    }

    /**
     * Re-route a pallet without physically moving it. Passing null clears the
     * destination (the pallet is no longer expected anywhere).
     *
     * Assigning the pallet's current location as its destination is treated as
     * an arrival rather than a target no move could ever satisfy.
     */
    public function assignDestination(
        Pallet $pallet,
        ?string $destination,
        ?Worker $operator = null,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): PalletMovement {
        return DB::transaction(function () use ($pallet, $destination, $operator, $recordedBy, $notes) {
            $locked = Pallet::whereKey($pallet->getKey())->lockForUpdate()->firstOrFail();

            $fromDestination = $locked->destination;
            $location = $locked->location;
            $reached = $destination !== null && $destination === $location;

            $locked->update([
                'destination' => $reached ? null : $destination,
                // A fresh target voids the previous arrival; unassigning leaves
                // the last arrival standing, since the pallet hasn't moved.
                'arrived_at' => match (true) {
                    $reached => now(),
                    $destination !== null => null,
                    default => $locked->arrived_at,
                },
            ]);

            // from == to location: the ledger reads as a re-route, not a move.
            return $this->append(
                $locked, $operator, $location, $location,
                $fromDestination, $reached ? null : $destination, $recordedBy, $notes,
            );
        });
    }

    /** Append the immutable ledger row describing one location/destination event. */
    private function append(
        Pallet $pallet,
        ?Worker $operator,
        ?string $fromLocation,
        ?string $toLocation,
        ?string $fromDestination,
        ?string $toDestination,
        ?User $recordedBy,
        ?string $notes,
    ): PalletMovement {
        return PalletMovement::create([
            'pallet_id' => $pallet->id,
            'worker_id' => $operator?->id,
            'from_location' => $fromLocation,
            'to_location' => $toLocation,
            'from_destination' => $fromDestination,
            'to_destination' => $toDestination,
            'moved_at' => now(),
            'notes' => $notes,
            'performed_by' => $recordedBy?->id,
        ]);
    }
}
