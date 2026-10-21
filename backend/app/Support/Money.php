<?php

namespace App\Support;

/**
 * The one place a money amount is turned into text on the server.
 *
 * Core (cost reports, worker pay) and modules (e.g. Enterprise wage groups)
 * call this instead of formatting amounts themselves, so every amount reads
 * the same way and carries its currency. The browser twin is
 * `resources/js/lib/money.js` (formatMoney).
 *
 * Output is locale-neutral — space thousands separator, dot decimal, code
 * suffix: `1 234.50 PLN`. `ext-intl` is not a requirement of this project, so
 * NumberFormatter is deliberately not used.
 */
class Money
{
    public static function format(float|int|string|null $amount, ?string $currency = null, int $decimals = 2): string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return '';
        }

        $currency = $currency !== null && $currency !== '' ? $currency : CurrencyRegistry::current();

        return number_format((float) $amount, $decimals, '.', ' ').' '.$currency;
    }
}
