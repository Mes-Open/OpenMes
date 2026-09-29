<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workstation;

/**
 * What the SN label station shows an operator at a bench
 * (`workstations.unit_label_actions`; empty = everything):
 *
 *   start        "Start unit on PSN" - the unit begins on its process serial
 *   issue        numbers from the product's sequences ("Issue number", batches)
 *   label        scan the PSN and the serial label: the two numbers are bound
 *   components   scan parts, lots and sub-assemblies into a unit
 *   subassembly  register a sub-assembly the order makes by its own serial
 *
 * Supervisors, admins and the whole-line view keep every action.
 */
final class UnitLabelActions
{
    public const START = 'start';

    public const ISSUE = 'issue';

    public const LABEL = 'label';

    public const COMPONENTS = 'components';

    public const SUBASSEMBLY = 'subassembly';

    public const ALL = [self::START, self::ISSUE, self::LABEL, self::COMPONENTS, self::SUBASSEMBLY];

    /** @return array<int, string> */
    public static function for(?User $user, ?Workstation $workstation): array
    {
        if (! $workstation || ! $user || $user->hasAnyRole(['Admin', 'Supervisor'])) {
            return self::ALL;
        }
        $pinned = array_values(array_intersect(self::ALL, (array) ($workstation->unit_label_actions ?? [])));

        return $pinned !== [] ? $pinned : self::ALL;
    }
}
