<?php

namespace App\Enums;

/**
 * Lifecycle of a shipment (one truck / load) at the dock (#400):
 * planned → loading (first scan) → confirmed, or cancelled before it leaves.
 */
enum ShipmentStatus: string
{
    case Planned = 'planned';
    case Loading = 'loading';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => __('Shipment planned'),
            self::Loading => __('Shipment loading'),
            self::Confirmed => __('Shipment confirmed'),
            self::Cancelled => __('Shipment cancelled'),
        };
    }

    /** Still on the dock: scans, edits and confirmation are allowed. */
    public function isActive(): bool
    {
        return $this === self::Planned || $this === self::Loading;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Planned->value, self::Loading->value];
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
