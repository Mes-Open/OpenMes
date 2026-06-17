<?php

namespace App\Services\Quality;

use App\Models\Issue;
use App\Models\MaterialLot;
use App\Models\User;

/**
 * Manual quality hold/release on a material lot. Putting a lot on hold moves it
 * to QUARANTINE (so the allocation engine won't pick it); releasing moves it
 * back to RELEASED. A hold can be linked to the Issue (non-conformance) that
 * triggered it. Inbound-inspection dispositions still drive lot status via
 * DispositionService — this is the general, manual path.
 */
class MaterialHoldService
{
    public function hold(MaterialLot $lot, string $reason, User $by, ?Issue $issue = null): MaterialLot
    {
        if (in_array($lot->status, [MaterialLot::STATUS_CONSUMED, MaterialLot::STATUS_REJECTED], true)) {
            throw new \DomainException("Cannot hold a {$lot->status} lot.");
        }

        $lot->update([
            'status' => MaterialLot::STATUS_QUARANTINE,
            'hold_reason' => $reason,
            'held_at' => now(),
            'held_by_id' => $by->id,
            'issue_id' => $issue?->id ?? $lot->issue_id,
            'released_at' => null,
            'released_by_id' => null,
        ]);

        return $lot->fresh();
    }

    public function release(MaterialLot $lot, User $by): MaterialLot
    {
        if (! in_array($lot->status, [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_RECEIVED], true)) {
            throw new \DomainException("Only a quarantined/received lot can be released (is {$lot->status}).");
        }

        $lot->update([
            'status' => MaterialLot::STATUS_RELEASED,
            'released_at' => now(),
            'released_by_id' => $by->id,
        ]);

        return $lot->fresh();
    }
}
