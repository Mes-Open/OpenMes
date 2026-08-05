<?php

namespace App\Enums;

/**
 * Why production on a work order was stopped (#182).
 *
 * A structured type instead of free text, because the reason drives what happens
 * next: whether a change request is expected, whether a downtime record should be
 * linked, and how the stop is reported. The prose reason stays alongside it — the
 * type is for the system, the reason is for the next person.
 */
enum WorkOrderStopType: string
{
    /** Planned or organisational stop: shift end, changeover, no operator. */
    case Operational = 'OPERATIONAL';

    /** Waiting on material — the shop floor cannot continue until it arrives. */
    case MaterialShortage = 'MATERIAL_SHORTAGE';

    /** The machine broke. Also the case that most often warrants a downtime record. */
    case MachineFailure = 'MACHINE_FAILURE';

    /** Quality put production on hold pending a decision. */
    case QualityHold = 'QUALITY_HOLD';

    /** The product, process or documentation must change before work continues. */
    case EngineeringChange = 'ENGINEERING_CHANGE';

    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Operational => __('Operational stop'),
            self::MaterialShortage => __('Material shortage'),
            self::MachineFailure => __('Machine failure'),
            self::QualityHold => __('Quality hold'),
            self::EngineeringChange => __('Engineering change'),
            self::Other => __('Other'),
        };
    }

    /**
     * Whether this kind of stop normally implies a configuration change.
     *
     * Advisory only — the caller states `requires_change` explicitly, because an
     * engineering stop can turn out to need nothing changed, and a quality hold can
     * turn out to need a new revision.
     */
    public function impliesChange(): bool
    {
        return $this === self::EngineeringChange;
    }

    /**
     * Whether a linked downtime record makes sense: the resource itself was idle,
     * as opposed to the order being held for a decision.
     */
    public function isResourceDowntime(): bool
    {
        return in_array($this, [self::MachineFailure, self::MaterialShortage], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
