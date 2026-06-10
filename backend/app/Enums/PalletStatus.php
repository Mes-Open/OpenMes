<?php

namespace App\Enums;

enum PalletStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Shipped = 'shipped';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Closed => __('Closed'),
            self::Shipped => __('Shipped'),
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
