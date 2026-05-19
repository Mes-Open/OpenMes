<?php

namespace App\Enums;

enum DowntimeKind: string
{
    case Planned = 'planned';
    case Unplanned = 'unplanned';
    case Changeover = 'changeover';

    public function countsAsAvailabilityLoss(): bool
    {
        return $this !== self::Planned;
    }

    public function label(): string
    {
        return match ($this) {
            self::Planned => __('Planned'),
            self::Unplanned => __('Unplanned'),
            self::Changeover => __('Changeover'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Planned => 'blue',
            self::Unplanned => 'red',
            self::Changeover => 'amber',
        };
    }

    public static function lossKinds(): array
    {
        return [self::Unplanned->value, self::Changeover->value];
    }
}
