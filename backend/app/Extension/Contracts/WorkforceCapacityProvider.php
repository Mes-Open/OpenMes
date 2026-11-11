<?php

namespace App\Extension\Contracts;

use Carbon\CarbonInterface;

/**
 * Where the finite-capacity scheduler asks how many people it can count on.
 *
 * Kept separate from {@see WorkforceProvider} on purpose: that contract is
 * already implemented outside core, and adding methods to it would stop every
 * existing implementation from loading. A provider that records crews, absences
 * and skills binds this one as well; an installation that records none of it
 * keeps the null implementation and scheduling stays machine-constrained only.
 *
 * Both methods answer for a whole window, so an implementation can fold shifts,
 * approved absences and break windows into one number. `null` means "nothing
 * here constrains it" — never "nobody": a provider that knows there is nobody
 * returns 0.
 *
 * Absences follow the payroll convention: an absence recorded for date D
 * removes a person from the shifts that *start* on D. A minute of the window
 * inside a shift belongs to that shift's start date, a minute outside every
 * shift to its calendar date — so Monday 22:00 – Tuesday 06:00 is Monday's
 * night shift, untouched by a Tuesday absence and blocked by a Monday one.
 */
interface WorkforceCapacityProvider
{
    /**
     * Operators available to staff the given line throughout [$start, $end).
     * A null line asks about operators not tied to any line. Someone counts
     * unless absent on a date the window belongs to (see above).
     */
    public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int;

    /**
     * Of those, how many hold every one of the given skills (with a valid
     * certification where the provider tracks expiry) throughout the window,
     * absences attributed to dates the same way.
     *
     * @param  list<int>  $skillIds
     */
    public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int;
}
