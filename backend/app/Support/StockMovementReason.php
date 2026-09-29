<?php

namespace App\Support;

/**
 * The system-written notes on stock movements, in one place.
 *
 * `stock_movements.reason` is free text: operators type their own, and the
 * allocation service writes a handful of fixed sentences. Those sentences are
 * shown translated, which means recognising them again at read time - so the
 * wording lives here once, used both to write a note and to turn a stored one
 * back into its catalogue key. Reword a template here and both sides move
 * together; a note that matches no template is a user's own and is shown as is.
 */
final class StockMovementReason
{
    /** Catalogue key => the parameters it takes, in order. */
    private const TEMPLATES = [
        'Allocated to batch #:batch (step :step)' => ['batch', 'step'],
        'Allocated to batch #:batch' => ['batch'],
        'Batch #:batch completed — leftover returned to stock' => ['batch'],
        'Batch #:batch scrap qty recorded' => ['batch'],
        'Batch #:batch cancelled — return to stock' => ['batch'],
        'Adjustment on batch #:batch' => ['batch'],
        'Batch #:batch — unused material returned to stock' => ['batch'],
    ];

    /** The English note stored on the movement. */
    public static function make(string $template, array $params): string
    {
        self::assertKnown($template);

        return strtr($template, self::placeholders($params));
    }

    /** A stored note in the current locale - or itself, when it is not one of ours. */
    public static function translate(?string $reason): ?string
    {
        if ($reason === null || $reason === '') {
            return $reason;
        }

        foreach (self::TEMPLATES as $template => $names) {
            // Quote the literal text between placeholders; each placeholder becomes a numeric capture.
            $literals = array_map(fn ($part) => preg_quote($part, '/'), preg_split('/:\w+/', $template));
            $pattern = '/^'.implode('(\d+)', $literals).'$/u';
            if (preg_match($pattern, $reason, $m)) {
                return __($template, array_combine($names, array_slice($m, 1)));
            }
        }

        return $reason;
    }

    private static function assertKnown(string $template): void
    {
        if (! array_key_exists($template, self::TEMPLATES)) {
            throw new \InvalidArgumentException("Unknown stock movement reason template: {$template}");
        }
    }

    /** @return array<string, string> */
    private static function placeholders(array $params): array
    {
        $out = [];
        foreach ($params as $name => $value) {
            $out[':'.$name] = (string) $value;
        }

        return $out;
    }
}
