<?php

namespace App\Extension\Contracts;

use Carbon\CarbonInterface;

/**
 * Optional companion to {@see WorkforceCapacityProvider}: a provider that can
 * say *which* people it counts lets the scheduler treat someone who staffs
 * several lines as one person, booked on at most one of them at a time.
 *
 * Each person is named by a key that is the same on every line they staff —
 * any stable string, e.g. `w:<worker id>`. A provider that only returns counts
 * does not implement this, and the scheduler keeps counting each line on its
 * own, exactly as before.
 *
 * A separate interface so existing capacity providers keep loading.
 */
interface WorkforceRosterAware
{
    /**
     * Who is assigned to each line, absences ignored.
     *
     * @return array<int, list<string>> line id => person keys
     */
    public function assignedOperatorKeys(): array;

    /**
     * Who of them staffs the line throughout [$start, $end), absences applied
     * the same way {@see WorkforceCapacityProvider::availableOperators()}
     * applies them — the count of this list equals that number. Null when the
     * line is not tracked.
     *
     * @return list<string>|null
     */
    public function presentOperatorKeys(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?array;
}
