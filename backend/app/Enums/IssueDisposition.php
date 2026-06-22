<?php

namespace App\Enums;

/**
 * Disposition decision for a non-conforming issue (#11): what happens to the
 * affected material. `Pending` is the default until quality decides.
 */
enum IssueDisposition: string
{
    case Pending = 'pending';
    case Scrap = 'scrap';
    case Rework = 'rework';
    case ReturnToSupplier = 'return_to_supplier';
    case UseAsIs = 'use_as_is';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Scrap => __('Scrap'),
            self::Rework => __('Rework'),
            self::ReturnToSupplier => __('Return to supplier'),
            self::UseAsIs => __('Use as is'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
