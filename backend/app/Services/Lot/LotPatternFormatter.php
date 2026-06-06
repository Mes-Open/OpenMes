<?php

namespace App\Services\Lot;

use Carbon\CarbonInterface;

/**
 * Renders token-based LOT patterns, e.g. "test-[date]-[seq]-[hour]".
 *
 * Supported tokens:
 *   [seq]        sequence number, zero-padded to pad_size
 *   [date]       date as Ymd (e.g. 20260606)
 *   [date:FMT]   date with a custom PHP date format (e.g. [date:y-m-d])
 *   [year]       4-digit year
 *   [month]      2-digit month
 *   [day]        2-digit day of month
 *   [hour]       2-digit hour (24h)
 *   [product]    product type code (empty string when no product type)
 *
 * Anything outside [] is a literal. A valid pattern contains exactly one [seq].
 */
class LotPatternFormatter
{
    public const TOKENS = ['seq', 'date', 'year', 'month', 'day', 'hour', 'product'];

    private const TOKEN_REGEX = '/\[([a-z]+)(?::([^\]]+))?\]/';

    /**
     * Render a pattern into a LOT number.
     */
    public function format(string $pattern, int $number, int $padSize, ?string $productCode, CarbonInterface $now): string
    {
        return preg_replace_callback(self::TOKEN_REGEX, function (array $m) use ($number, $padSize, $productCode, $now) {
            return match ($m[1]) {
                'seq' => str_pad((string) $number, $padSize, '0', STR_PAD_LEFT),
                'date' => $now->format($m[2] ?? 'Ymd'),
                'year' => $now->format('Y'),
                'month' => $now->format('m'),
                'day' => $now->format('d'),
                'hour' => $now->format('H'),
                'product' => $productCode ?? '',
                default => $m[0], // unreachable for validated patterns
            };
        }, $pattern);
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
            $errors[] = 'Unknown tokens: ['.implode('], [', $unknown).']. Allowed: ['.implode('], [', self::TOKENS).'].';
        }

        $seqCount = count(array_keys($matches[1], 'seq', true));
        if ($seqCount !== 1) {
            $errors[] = 'Pattern must contain exactly one [seq] token.';
        }

        // A format argument is only meaningful on [date]
        foreach ($matches[1] as $i => $token) {
            if ($token !== 'date' && ($matches[2][$i] ?? '') !== '') {
                $errors[] = "Token [{$token}] does not accept a format argument.";
            }
        }

        // Stray brackets left after removing valid token syntax
        $stripped = preg_replace(self::TOKEN_REGEX, '', $pattern);
        if (str_contains($stripped, '[') || str_contains($stripped, ']')) {
            $errors[] = 'Pattern contains unmatched or malformed brackets.';
        }

        return $errors;
    }
}
