<?php

namespace App\Enums;

enum PalletStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Shipped = 'shipped';

    /**
     * Status labels use pallet-specific translation keys (not the shared
     * 'Open'/'Closed'/'Shipped' keys): in gendered locales the adjective must
     * agree with "pallet" (pl: "Otwarta", not the verb "Otwórz" the shared
     * 'Open' key resolves to on buttons).
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => __('Pallet open'),
            self::Closed => __('Pallet closed'),
            self::Shipped => __('Pallet shipped'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::Closed => 'blue',
            self::Shipped => 'gray',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
