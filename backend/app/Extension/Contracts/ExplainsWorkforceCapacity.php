<?php

namespace App\Extension\Contracts;

use Carbon\CarbonInterface;

/**
 * Optional companion to {@see WorkforceCapacityProvider}: a provider that can
 * say who it counted lets the scheduler explain a people shortfall — who is
 * assigned to a line, who of them is away in a window, and what the people and
 * skills it names are called.
 *
 * A separate interface so existing capacity providers keep loading; the
 * scheduler asks only when a provider implements it, and otherwise reports
 * the bare numbers.
 */
interface ExplainsWorkforceCapacity
{
    /**
     * How many people are assigned to the line; null when nobody is, i.e. the
     * line's staffing is not tracked.
     */
    public function assignedCount(?int $lineId): ?int;

    /**
     * Workers assigned to the line who are absent on a date the window belongs
     * to — the start date of each shift it covers, the calendar date outside
     * shifts (see {@see WorkforceCapacityProvider}).
     *
     * @return list<int>
     */
    public function absentWorkerIds(?int $lineId, CarbonInterface $start, CarbonInterface $end): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string> worker id => display name
     */
    public function workerNames(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string> skill id => display name; empty when skills are not recorded
     */
    public function skillNames(array $ids): array;
}
