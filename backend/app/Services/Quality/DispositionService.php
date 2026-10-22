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
 * corresponding state change to any MaterialLot linked through inspection_id,
 * creating that lot when the inspection has none yet (lot tracking on).
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
    private InspectionLotCreator $lots;

    public function __construct(?InspectionLotCreator $lots = null)
    {
        $this->lots = $lots ?? new InspectionLotCreator;
    }

    /**
     * Apply a disposition to the inspection and re-sync linked material lots.
     *
     * Transactional: if the lot status update fails, the inspection update
     * is rolled back so the two never disagree.
     *
     * @throws \InvalidArgumentException when the disposition is not a known value
     * @throws \DomainException when the caller tries to reset to 'pending', or
     *                          the inspection is not completed yet
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
            // Lock the inspection so two decisions taken at once queue up
            // instead of each finding no lot and each creating one.
            $locked = Inspection::whereKey($inspection->getKey())->lockForUpdate()->firstOrFail();

            // A decision on material nobody has inspected yet would stock it
            // in, and completing the inspection afterwards never revisits an
            // existing lot — a later fail would leave it released.
            if ($locked->isPending()) {
                throw new \DomainException(__('Complete the inspection before applying a disposition.'));
            }

            $locked->update([
                'disposition' => $disposition,
                'disposition_notes' => $notes,
                'disposition_by_id' => $by->id,
                'disposition_at' => now(),
            ]);

            $lotStatus = $this->mapDispositionToLotStatus($disposition);
            if ($lotStatus === null) {
                return;
            }

            // An inspector without the disposition right completes an
            // inspection without stocking anything in; the lot comes into
            // being here, with the decision.
            $lots = MaterialLot::where('inspection_id', $locked->id)->get();
            if ($lots->isEmpty()) {
                $this->lots->ensureFromInspection($locked, $lotStatus, $by);

                return;
            }

            foreach ($lots as $lot) {
                $this->moveLot($lot, $lotStatus);
            }
        });

        return $inspection->fresh();
    }

    /**
     * Put a lot into the decided status. A lot recorded while held starts
     * with nothing available; releasing it makes the received quantity
     * available, unless something was already drawn from it — then its
     * remaining quantity is left as it is. Holding a lot changes only its
     * status: picking reads released lots only, and the remaining quantity is
     * still there when the lot is released again.
     */
    private function moveLot(MaterialLot $lot, string $lotStatus): void
    {
        $attributes = ['status' => $lotStatus];

        if ($lotStatus === MaterialLot::STATUS_RELEASED
            && (float) $lot->quantity_available <= 0
            && ! $lot->picks()->exists()
            && ! $lot->consumptions()->exists()) {
            $attributes['quantity_available'] = $lot->quantity_received;
        }

        $lot->update($attributes);
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
