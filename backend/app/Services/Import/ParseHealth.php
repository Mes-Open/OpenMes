<?php

namespace App\Services\Import;

/**
 * What the preview can tell the user about how their file was read.
 *
 * The reader is deliberately forgiving — it scrubs invalid UTF-8 rather than
 * failing, and falls back to a comma when it cannot detect a delimiter — so a
 * mis-declared encoding or separator produces a readable-looking table with
 * dropped accents or every column crammed into one. That silence is the
 * problem: the user picks both settings before seeing a single row. These
 * checks turn each failure into a visible warning with the setting that would
 * fix it.
 */
class ParseHealth
{
    /** Sequences that appear when UTF-8 bytes are decoded as a single-byte code page. */
    private const MOJIBAKE = ['Ã¡', 'Ã©', 'Ã³', 'Ã„', 'Ã…', 'Å¼', 'Å„', 'Ä…', 'Å›', 'Ä‡', 'â€', 'Ãƒ'];

    /** Bytes worth trying as a delimiter when the chosen one yields one column. */
    private const CANDIDATES = ['semicolon' => ';', 'comma' => ',', 'tab' => "\t"];

    /**
     * @param  array{delimiter?: string, encoding?: string}  $options  as parsed
     * @param  array{headers: list<string>, rows: list<array<string, mixed>>, total: int}  $parsed
     * @return list<array{code: string, message: string, fix: array<string, string>|null}>
     */
    public function inspect(string $path, array $options, array $parsed): array
    {
        $warnings = [];
        $isSpreadsheet = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true);

        // A spreadsheet carries its own encoding and column structure; neither
        // setting applies, so neither can be wrong.
        if (! $isSpreadsheet) {
            $raw = @file_get_contents($path);

            if (is_string($raw) && $raw !== '') {
                $warnings = array_merge(
                    $warnings,
                    $this->encodingWarnings($raw, (string) ($options['encoding'] ?? 'utf-8'), $parsed),
                    $this->delimiterWarnings($raw, $parsed),
                );
            }
        }

        if ($parsed['total'] === 0) {
            $warnings[] = $this->warning(
                'no_rows',
                __('The file has a header row but no data rows.'),
            );
        }

        return $warnings;
    }

    /** @return list<array{code: string, message: string, fix: array<string, string>|null}> */
    private function encodingWarnings(string $raw, string $encoding, array $parsed): array
    {
        // Declared UTF-8 but the bytes are not: the reader scrubs what it cannot
        // decode, so accented characters are silently dropped from every row.
        if ($encoding === 'utf-8' && ! mb_check_encoding($raw, 'UTF-8')) {
            return [$this->warning(
                'encoding_not_utf8',
                __('This file is not valid UTF-8, so accented characters are being dropped. Excel on Windows usually saves Polish text as Windows-1250.'),
                ['encoding' => 'windows-1250'],
            )];
        }

        // The mirror image: a UTF-8 file read as a single-byte code page, which
        // turns every accent into two Latin characters.
        if ($encoding !== 'utf-8' && $this->looksLikeMojibake($parsed)) {
            return [$this->warning(
                'encoding_mojibake',
                __('Accented characters look doubled (e.g. "Ä…" instead of "ą") — the file is probably already UTF-8.'),
                ['encoding' => 'utf-8'],
            )];
        }

        return [];
    }

    /** @return list<array{code: string, message: string, fix: array<string, string>|null}> */
    private function delimiterWarnings(string $raw, array $parsed): array
    {
        if (count($parsed['headers']) > 1) {
            return [];
        }

        $headerLine = strtok($raw, "\r\n") ?: '';

        // One column only makes sense if no other candidate would split it.
        foreach (self::CANDIDATES as $name => $char) {
            if (substr_count($headerLine, $char) > 0) {
                return [$this->warning(
                    'delimiter_single_column',
                    __('Every column landed in one — the separator looks wrong for this file.'),
                    ['delimiter' => $name],
                )];
            }
        }

        return [];
    }

    private function looksLikeMojibake(array $parsed): bool
    {
        $sample = implode(' ', $parsed['headers']);

        foreach (array_slice($parsed['rows'], 0, 5) as $row) {
            $sample .= ' '.implode(' ', array_map('strval', $row));
        }

        foreach (self::MOJIBAKE as $needle) {
            if (str_contains($sample, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{code: string, message: string, fix: array<string, string>|null} */
    private function warning(string $code, string $message, ?array $fix = null): array
    {
        return ['code' => $code, 'message' => $message, 'fix' => $fix];
    }
}
