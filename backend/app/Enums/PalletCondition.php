<?php

namespace App\Enums;

/**
 * Physical condition of a pallet as the warehouse found it - not its quality
 * verdict, which QC owns (pallets.quality_status).
 */
enum PalletCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';

    /** Pallet-specific keys, so gendered locales agree with "pallet". */
    public function label(): string
    {
        return match ($this) {
            self::Good => __('Condition good'),
            self::Damaged => __('Condition damaged'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Good => 'green',
            self::Damaged => 'red',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $c) => $carry + [$c->value => $c->label()],
            [],
        );
    }
}
