<?php

namespace App\Enums;

/**
 * What a production-change request asks for.
 *
 * CHANGE proposes new values for a running order's changeable fields. CANCEL asks
 * for the order to be cancelled — the ERP's way of withdrawing an order that is
 * already in production, which a manager has to decide on rather than the ERP.
 */
enum ChangeRequestType: string
{
    case Change = 'CHANGE';
    case Cancellation = 'CANCEL';

    public function label(): string
    {
        return match ($this) {
            self::Change => __('Change'),
            self::Cancellation => __('Cancellation'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
