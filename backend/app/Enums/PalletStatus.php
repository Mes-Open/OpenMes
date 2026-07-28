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

    /**
     * value => label map, for pages that render a status column or filter from
     * a raw string. The enum owns its labels so the admin and logistics views
     * can't drift apart.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $c) => $carry + [$c->value => $c->label()],
            [],
        );
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
