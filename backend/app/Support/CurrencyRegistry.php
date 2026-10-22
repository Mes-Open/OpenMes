<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The installation's single currency (ISO 4217), set in Settings → System.
 *
 * One currency per installation: every pay rate and every production cost
 * amount in core is expressed in it. There is no FX conversion — per-record
 * currencies on materials or additional costs are summed numerically and the
 * cost report flags the mix.
 *
 * This list is the only allowlist: the settings form, the API setting endpoint
 * and the dropdown all read it. A plant that needs a code not listed here adds
 * one line to CURRENCIES.
 */
class CurrencyRegistry
{
    public const SETTING_KEY = 'default_currency';

    public const DEFAULT = 'PLN';

    /**
     * Supported ISO 4217 codes and their English names (translated in the UI).
     *
     * @var array<string, string>
     */
    public const CURRENCIES = [
        'PLN' => 'Polish Złoty',
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
        'GBP' => 'British Pound',
        'CHF' => 'Swiss Franc',
        'CZK' => 'Czech Koruna',
        'SEK' => 'Swedish Krona',
        'NOK' => 'Norwegian Krone',
        'DKK' => 'Danish Krone',
        'HUF' => 'Hungarian Forint',
        'RON' => 'Romanian Leu',
        'UAH' => 'Ukrainian Hryvnia',
        'VND' => 'Vietnamese Đồng',
        'BGN' => 'Bulgarian Lev',
        'RSD' => 'Serbian Dinar',
        'TRY' => 'Turkish Lira',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
        'JPY' => 'Japanese Yen',
        'CNY' => 'Chinese Yuan',
        'INR' => 'Indian Rupee',
        'BRL' => 'Brazilian Real',
        'MXN' => 'Mexican Peso',
    ];

    /**
     * Scoped container key: Octane resets scoped bindings between requests, so
     * a currency changed in one worker reaches every other worker on its next
     * request. Long-lived processes that never reset the container (queue
     * workers) re-read after CACHE_SECONDS — same approach as ProductionFlow.
     */
    private const CACHE_KEY = 'openmes.currency.cache';

    private const CACHE_SECONDS = 10;

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::CURRENCIES);
    }

    /**
     * For a dropdown: [['code' => 'PLN', 'name' => 'Polish Złoty'], …].
     *
     * @return array<int, array{code: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $code, string $name) => ['code' => $code, 'name' => $name],
            array_keys(self::CURRENCIES),
            self::CURRENCIES,
        );
    }

    /** Exact match only — callers normalise() user input first. */
    public static function isValid(string $code): bool
    {
        return array_key_exists($code, self::CURRENCIES);
    }

    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * The effective currency: the stored setting when it is a supported code,
     * else the DEFAULT_CURRENCY config value when supported, else PLN.
     */
    public static function current(): string
    {
        $cache = self::cache();
        if ($cache['code'] !== null && microtime(true) - $cache['at'] < self::CACHE_SECONDS) {
            return $cache['code'];
        }

        $stored = null;
        try {
            $value = DB::table('system_settings')->where('key', self::SETTING_KEY)->value('value');
            if ($value !== null) {
                // JSON column; tolerate a legacy raw string too.
                $decoded = json_decode((string) $value, true);
                $stored = is_string($decoded) ? $decoded : (string) $value;
            }
        } catch (\Throwable) {
            // No database yet (installer) — fall through to config.
            return self::fallback();
        }

        $code = is_string($stored) && self::isValid($stored) ? $stored : self::fallback();

        $cache['code'] = $code;
        $cache['at'] = microtime(true);

        return $code;
    }

    /** Persist the choice. Unsupported codes are refused rather than stored. */
    public static function save(string $code): void
    {
        if (! self::isValid($code)) {
            throw new \InvalidArgumentException("Unsupported currency code: {$code}");
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($code), 'updated_at' => now()],
        );

        self::forget();
    }

    /**
     * Amount columns that hold the installation currency unless a record was
     * explicitly priced in another one (e.g. imported from an ERP).
     *
     * @var array<string, string> table => currency column
     */
    public const RELABELLED_COLUMNS = [
        'materials' => 'price_currency',
        'material_allocations' => 'price_currency_snapshot',
        'additional_costs' => 'currency',
    ];

    /**
     * Switch the installation to another currency.
     *
     * One currency per installation: amounts recorded in the previous
     * installation currency are relabelled to the new one (the numbers are
     * kept — there is no FX conversion), so the cost report does not read
     * "PLN" lines under an "EUR" total. Amounts recorded in any other
     * currency are left alone and stay flagged as foreign.
     */
    public static function change(string $code): void
    {
        if (! self::isValid($code)) {
            throw new \InvalidArgumentException("Unsupported currency code: {$code}");
        }

        self::forget();
        $previous = self::current();

        DB::transaction(function () use ($code, $previous): void {
            self::save($code);

            if ($previous === $code) {
                return;
            }

            foreach (self::RELABELLED_COLUMNS as $table => $column) {
                DB::table($table)->where($column, $previous)->update([$column => $code]);
            }
        });

        self::forget();
    }

    /** Drop the cached code, e.g. after the setting was written some other way. */
    public static function forget(): void
    {
        self::cache()['code'] = null;
    }

    private static function cache(): \ArrayObject
    {
        $app = app();
        if (! $app->bound(self::CACHE_KEY)) {
            $app->scoped(self::CACHE_KEY, fn () => new \ArrayObject(['code' => null, 'at' => 0.0]));
        }

        return $app->make(self::CACHE_KEY);
    }

    private static function fallback(): string
    {
        $configured = self::normalise((string) config('openmmes.default_currency', self::DEFAULT));

        return self::isValid($configured) ? $configured : self::DEFAULT;
    }
}
