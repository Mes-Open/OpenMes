<?php

namespace App\Services\Quality;

use App\Models\Inspection;
use App\Models\MaterialLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ISA-95 Quality Disposition service.
 *
 * Records a quality decision against an Inspection and propagates the
 * corresponding state change to any MaterialLot linked through inspection_id.
 *
 * Disposition vocabulary (ISA-95 Part 4):
 *   accept                 → lot released
 *   accept_with_deviation  → lot released (with documented deviation)
 *   rework                 → lot quarantined while rework is performed
 *   quarantine             → lot quarantined pending further decision
 *   scrap                  → lot rejected (discarded)
 *   return_to_supplier     → lot rejected (returned)
 *   reject                 → lot rejected (no further action)
 */
class DispositionService
{
    /**
     * Apply a disposition to the inspection and re-sync linked material lots.
     *
     * Transactional: if the lot status update fails, the inspection update
     * is rolled back so the two never disagree.
     *
     * @throws \InvalidArgumentException when the disposition is not a known value
     * @throws \DomainException          when the caller tries to reset to 'pending'
     */
    public function apply(Inspection $inspection, string $disposition, ?string $notes, User $by): Inspection
    {
        if (! in_array($disposition, Inspection::DISPOSITIONS, true)) {
            throw new \InvalidArgumentException("invalid disposition: {$disposition}");
        }
        if ($disposition === Inspection::DISPOSITION_PENDING) {
            throw new \DomainException('disposition cannot be reset to pending');
        }

        DB::transaction(function () use ($inspection, $disposition, $notes, $by) {
            $inspection->update([
                'disposition' => $disposition,
                'disposition_notes' => $notes,
                'disposition_by_id' => $by->id,
                'disposition_at' => now(),
            ]);

            $lotStatus = $this->mapDispositionToLotStatus($disposition);
            if ($lotStatus !== null) {
                MaterialLot::where('inspection_id', $inspection->id)
                    ->update(['status' => $lotStatus]);
            }
        });

        return $inspection->fresh();
    }

    /**
     * Translate a disposition into the matching MaterialLot status,
     * or null if the disposition has no lot-level effect.
     */
    public function mapDispositionToLotStatus(string $disposition): ?string
    {
        return match ($disposition) {
            Inspection::DISPOSITION_ACCEPT,
            Inspection::DISPOSITION_ACCEPT_WITH_DEVIATION => MaterialLot::STATUS_RELEASED,
            Inspection::DISPOSITION_QUARANTINE,
            Inspection::DISPOSITION_REWORK => MaterialLot::STATUS_QUARANTINE,
            Inspection::DISPOSITION_SCRAP,
            Inspection::DISPOSITION_RETURN_TO_SUPPLIER,
            Inspection::DISPOSITION_REJECT => MaterialLot::STATUS_REJECTED,
            default => null,
        };
    }
}
