<?php

namespace App\Services\Lot;

use Carbon\CarbonInterface;

/**
 * Renders token-based LOT patterns, e.g. "test-[date]-[seq]-[hour]".
 *
 * Supported tokens:
 *   [seq]        sequence number, zero-padded to pad_size
 *   [year]       4-digit year            [year2]   2-digit year        [year1]  last digit of the year
 *   [month]      2-digit month           [day]     2-digit day of month
 *   [doy]        3-digit day of the year, 1-based (Jan 1 -> 001)
 *   [week]       2-digit ISO week        [weekday] 1-7, Monday = 1
 *   [hour]       2-digit hour (24h)
 *   [product]    product type code (empty string when no product type)
 *   [date]       date as Ymd (e.g. 20260606)
 *   [date:FMT]   advanced: any PHP date format (e.g. [date:y-m-d]); aliases
 *                y1 = last digit of the year, o1 = last digit of the ISO year
 *
 * Anything outside [] is a literal. A valid pattern contains exactly one [seq].
 */
class LotPatternFormatter
{
    public const TOKENS = ['seq', 'year', 'year2', 'year1', 'month', 'day', 'doy', 'week', 'weekday', 'hour', 'product', 'date'];

    private const TOKEN_REGEX = '/\[([a-z][a-z0-9]*)(?::([^\]]+))?\]/';

    /**
     * Render a pattern into a LOT number.
     */
    public function format(string $pattern, int $number, int $padSize, ?string $productCode, CarbonInterface $now): string
    {
        return preg_replace_callback(self::TOKEN_REGEX, function (array $m) use ($number, $padSize, $productCode, $now) {
            return match ($m[1]) {
                'seq' => str_pad((string) $number, $padSize, '0', STR_PAD_LEFT),
                // A plain [date] has no format group at all, so read it defensively.
                'date' => match ($m[2] ?? 'Ymd') {
                    'y1' => (string) ($now->year % 10),
                    'o1' => (string) ((int) $now->format('o') % 10),
                    default => $now->format($m[2] ?? 'Ymd'),
                },
                'year' => $now->format('Y'),
                'year2' => $now->format('y'),
                'year1' => (string) ($now->year % 10),
                'month' => $now->format('m'),
                'day' => $now->format('d'),
                'doy' => str_pad((string) ($now->dayOfYear), 3, '0', STR_PAD_LEFT),
                'week' => $now->format('W'),
                'weekday' => $now->format('N'),
                'hour' => $now->format('H'),
                'product' => $productCode ?? '',
                default => $m[0], // unreachable for validated patterns
            };
        }, $pattern);
    }

    /**
     * The tokens as the pattern editor lists them: what each means and what it
     * gives right now, so nobody has to know a date-format letter to build
     * "day of year - year - counter".
     *
     * @return array<int, array{token: string, label: string, example: string}>
     */
    public function describe(CarbonInterface $now, int $padSize = 4): array
    {
        $labels = [
            'seq' => __('Counter, zero-padded to the pad size'),
            'year' => __('Year, 4 digits'),
            'year2' => __('Year, 2 digits'),
            'year1' => __('Year, last digit'),
            'month' => __('Month, 2 digits'),
            'day' => __('Day of month, 2 digits'),
            'doy' => __('Day of year, 3 digits'),
            'week' => __('ISO week, 2 digits'),
            'weekday' => __('Weekday, 1 = Monday … 7 = Sunday'),
            'hour' => __('Hour, 24h'),
            'product' => __('Product type code'),
            'date' => __('Date as YYYYMMDD'),
        ];

        return array_map(fn (string $token) => [
            'token' => $token,
            'label' => $labels[$token],
            'example' => $token === 'product' ? __('e.g. :code', ['code' => 'PT-01']) : $this->format("[{$token}]", 1, $padSize, null, $now),
        ], self::TOKENS);
    }

    /**
     * Validate a pattern. Returns a list of error messages (empty = valid).
     *
     * @return string[]
     */
    public function validate(string $pattern): array
    {
        $errors = [];

        preg_match_all(self::TOKEN_REGEX, $pattern, $matches);

        $unknown = array_diff(array_unique($matches[1]), self::TOKENS);
        if ($unknown) {
            $errors[] = __('Unknown tokens: :tokens. Allowed: :allowed.', ['tokens' => '['.implode('], [', $unknown).']', 'allowed' => '['.implode('], [', self::TOKENS).']']);
        }

        $seqCount = count(array_keys($matches[1], 'seq', true));
        if ($seqCount !== 1) {
            $errors[] = __('Pattern must contain exactly one [seq] token.');
        }

        // A format argument is only meaningful on [date]
        foreach ($matches[1] as $i => $token) {
            if ($token !== 'date' && ($matches[2][$i] ?? '') !== '') {
                $errors[] = __('Token [:token] does not accept a format argument.', ['token' => $token]);
            }
        }

        // Stray brackets left after removing valid token syntax
        $stripped = preg_replace(self::TOKEN_REGEX, '', $pattern);
        if (str_contains($stripped, '[') || str_contains($stripped, ']')) {
            $errors[] = __('Pattern contains unmatched or malformed brackets.');
        }

        return $errors;
    }
}
