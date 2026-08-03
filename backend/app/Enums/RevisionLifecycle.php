<?php

namespace App\Enums;

/**
 * Product revision lifecycle. A revision starts as DRAFT (editable, not
 * selectable for production), is RELEASED once its configuration is approved
 * (immutable, selectable for new work orders), and finally OBSOLETE (retained
 * for historical traceability, no longer selectable). Ordered draft < released
 * < obsolete.
 */
enum RevisionLifecycle: string
{
    case Draft = 'draft';
    case Released = 'released';
    case Obsolete = 'obsolete';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Released => __('Released'),
            self::Obsolete => __('Obsolete'),
        };
    }

    /** Tailwind badge classes used by the front-end lifecycle badge. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-200 text-gray-700',
            self::Released => 'bg-green-100 text-green-800',
            self::Obsolete => 'bg-amber-100 text-amber-800',
        };
    }

    /** Only released revisions may be selected for new production orders. */
    public function isSelectable(): bool
    {
        return $this === self::Released;
    }
}
