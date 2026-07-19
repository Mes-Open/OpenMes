<?php

namespace App\Enums;

/**
 * Customer loyalty tier. Drives priority scoring (see PriorityScoringService,
 * phase 2) and is displayed as a badge on the customer list and work-order
 * views. Ordered bronze < silver < gold < vip.
 */
enum Tier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Vip = 'vip';

    public function label(): string
    {
        return match ($this) {
            self::Bronze => __('Bronze'),
            self::Silver => __('Silver'),
            self::Gold => __('Gold'),
            self::Vip => __('VIP'),
        };
    }

    /** Tailwind badge classes used by the front-end tier badge. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Bronze => 'bg-amber-100 text-amber-800',
            self::Silver => 'bg-gray-200 text-gray-700',
            self::Gold => 'bg-yellow-100 text-yellow-800',
            self::Vip => 'bg-purple-100 text-purple-800',
        };
    }

    /** Ordinal rank (bronze=0 … vip=3) for comparing/upgrading tiers. */
    public function rank(): int
    {
        return match ($this) {
            self::Bronze => 0,
            self::Silver => 1,
            self::Gold => 2,
            self::Vip => 3,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
