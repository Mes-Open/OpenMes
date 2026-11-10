<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Timestamps reported by machines, in the plant's timezone.
 *
 * Machine timestamp columns are `timestamp without time zone`, and Eloquent
 * writes a Carbon value as its own wall-clock time without converting it. A
 * reading stamped `2026-11-10T10:00:00Z` therefore has to be moved into the
 * timezone set in OpenMES (TimezoneRegistry) before it is stored, or it lands
 * hours off next to everything the app stamps with `now()`: the counter's
 * out-of-order guard, the reading history and "today" totals all compare the
 * two.
 *
 * Accepted inputs:
 *   - ISO 8601 / date strings with an offset (`Z`, `+02:00`) — converted;
 *   - date strings without an offset — taken as plant time already;
 *   - Unix epochs in seconds or milliseconds (int or numeric string), the
 *     usual shape from PLCs and IoT gateways.
 */
final class MachineTime
{
    /**
     * Epochs at or above this are milliseconds: 1e11 seconds is the year 5138,
     * while 1e11 milliseconds is March 1973.
     */
    private const MILLISECONDS_FROM = 100_000_000_000;

    /**
     * Parse a machine-reported timestamp into plant time. Null for an empty
     * value; throws on a value that is not a date at all.
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $epoch = (float) $value;
            $at = $epoch >= self::MILLISECONDS_FROM
                ? Carbon::createFromTimestampMs($epoch)
                : Carbon::createFromTimestamp($epoch);

            return self::toPlant($at);
        }

        if ($value instanceof \DateTimeInterface) {
            return self::toPlant(Carbon::instance($value));
        }

        // A string without an offset is parsed in the default (plant) zone.
        return self::toPlant(Carbon::parse((string) $value));
    }

    /** The same instant, expressed in the plant timezone. */
    public static function toPlant(\DateTimeInterface $at): Carbon
    {
        return Carbon::instance($at)->setTimezone((string) config('app.timezone'));
    }
}
