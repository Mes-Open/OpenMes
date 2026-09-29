<?php

namespace App\Services\Telemetry;

/**
 * Counts leave this building as size bands, never as numbers.
 *
 * "Does anyone actually use Materials?" is answered just as well by "50-199"
 * as by 137, and the band stops being a business signal: an exact count of
 * work orders is a statement about a factory's throughput, and that is the
 * customer's information, not ours. The same applies to error counts, where a
 * precise figure would also be a timing oracle for how hard a site is running.
 */
class Buckets
{
    /** @var list<array{0:int,1:string}> upper bound (inclusive), label */
    private const BANDS = [
        [0, '0'],
        [9, '1-9'],
        [49, '10-49'],
        [199, '50-199'],
        [999, '200-999'],
        [4999, '1k-5k'],
        [19999, '5k-20k'],
    ];

    private const OVERFLOW = '20k+';

    public static function of(int $count): string
    {
        if ($count < 0) {
            $count = 0;
        }

        foreach (self::BANDS as [$max, $label]) {
            if ($count <= $max) {
                return $label;
            }
        }

        return self::OVERFLOW;
    }

    /** Every label this can produce — the test asserts no raw number escaped. */
    public static function labels(): array
    {
        return [...array_column(self::BANDS, 1), self::OVERFLOW];
    }
}
