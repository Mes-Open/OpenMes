<?php

namespace App\Services\Import;

use App\Import\Contracts\EntityImporter;
use Carbon\Carbon;

/**
 * Applies a column mapping (file header → importer field) to one file row and
 * coerces each value to the field's declared type, so the entity importers
 * receive the same canonical rows the ERP API sends them.
 *
 * Empty cells are omitted, not sent as '' — an importer treats a missing key
 * as "leave alone on update", which is what a blank Excel cell means.
 */
class RowMapper
{
    public const IGNORE = '_ignore';

    public const CUSTOM_PREFIX = 'custom:';

    private const TRUE_WORDS = ['1', 'true', 'yes', 'y', 'tak', 't', 'on', 'active', 'aktywny'];

    private const FALSE_WORDS = ['0', 'false', 'no', 'n', 'nie', 'off', 'inactive', 'nieaktywny'];

    /**
     * @param  array<string, mixed>  $fileRow  header => cell (plus SpreadsheetReader::ROW_KEY)
     * @param  array<string, string>  $mapping  header => field | `_ignore` | `custom:<key>`
     * @return array<string, mixed> field => typed value; custom keys under `custom`
     *
     * @throws RowMappingException
     */
    public function map(array $fileRow, array $mapping, EntityImporter $importer): array
    {
        $fields = $importer->fields();
        $out = [];

        foreach ($mapping as $header => $target) {
            $target = (string) $target;

            if ($target === '' || $target === self::IGNORE) {
                continue;
            }

            $value = trim((string) ($fileRow[$header] ?? ''));

            if ($value === '') {
                continue;
            }

            if (str_starts_with($target, self::CUSTOM_PREFIX)) {
                if ($importer->allowsCustomFields()) {
                    $key = substr($target, strlen(self::CUSTOM_PREFIX));

                    if ($key !== '') {
                        $out['custom'][$key] = $value;
                    }
                }

                continue;
            }

            if (! isset($fields[$target])) {
                continue; // a stale profile naming a field this entity no longer has
            }

            $out[$target] = $this->coerce($target, $value, $fields[$target]['type'] ?? 'text');
        }

        return $out;
    }

    /**
     * Every cell in the row whose value its mapped field would reject, keyed by
     * file header.
     *
     * map() throws on the first bad cell because an importer cannot use a
     * half-mapped row; the preview needs all of them at once, on the row the
     * reader actually produced, so the screen can mark each offending cell.
     *
     * @param  array<string, mixed>  $fileRow  header => cell
     * @param  array<string, string>  $mapping  header => field | `_ignore` | `custom:<key>`
     * @return array<string, string> header => reason
     */
    public function problems(array $fileRow, array $mapping, EntityImporter $importer): array
    {
        $fields = $importer->fields();
        $problems = [];

        foreach ($mapping as $header => $target) {
            $target = (string) $target;

            // Ignored and custom columns are never coerced, so they cannot fail.
            if ($target === '' || $target === self::IGNORE || str_starts_with($target, self::CUSTOM_PREFIX)) {
                continue;
            }

            if (! isset($fields[$target])) {
                continue; // a stale profile naming a field this entity dropped
            }

            $value = trim((string) ($fileRow[$header] ?? ''));

            if ($value === '') {
                continue; // blank means "leave alone", never an error
            }

            try {
                $this->coerce($target, $value, $fields[$target]['type'] ?? 'text');
            } catch (RowMappingException $e) {
                $problems[$header] = $e->getMessage();
            }
        }

        return $problems;
    }

    /**
     * @throws RowMappingException
     */
    private function coerce(string $field, string $value, string $type): mixed
    {
        switch ($type) {
            case 'number':
                $normalised = $this->normaliseNumber($value);

                if ($normalised === null) {
                    throw new RowMappingException($field, __("':value' is not a number", ['value' => $value]));
                }

                return (float) $normalised;

            case 'integer':
                $normalised = $this->normaliseNumber($value) ?? '';

                if (! preg_match('/^-?\d+$/', $normalised)) {
                    throw new RowMappingException($field, __("':value' is not a whole number", ['value' => $value]));
                }

                return (int) $normalised;

            case 'bool':
                $word = mb_strtolower($value);

                if (in_array($word, self::TRUE_WORDS, true)) {
                    return true;
                }

                if (in_array($word, self::FALSE_WORDS, true)) {
                    return false;
                }

                throw new RowMappingException($field, __("':value' is not yes/no", ['value' => $value]));
            case 'date':
                try {
                    return Carbon::parse($value)->toDateString();
                } catch (\Throwable) {
                    throw new RowMappingException($field, __("':value' is not a date", ['value' => $value]));
                }

            default:
                return $value;
        }
    }

    /**
     * A spreadsheet number as PHP reads it, or null when it is not one.
     *
     * Handles what Excel and Polish exports actually write: a space or NBSP
     * as thousands separator ("1 250,5"), a decimal comma ("24,50"), and both
     * separators together in either convention ("1,234.50", "1.234,50") — when
     * both appear, the one that comes last is the decimal mark. A lone comma is
     * read as the decimal mark, so "1,235" is 1.235: that is what a Polish CSV
     * means, and XLSX cells arrive unformatted (see SpreadsheetReader) so the
     * ambiguity never comes from Excel.
     */
    private function normaliseNumber(string $value): ?string
    {
        $v = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $value);

        $lastComma = strrpos($v, ',');
        $lastDot = strrpos($v, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $v = $lastComma > $lastDot
                ? str_replace('.', '', $v)               // 1.234,50
                : str_replace(',', '', $v);              // 1,234.50
        } elseif (substr_count($v, '.') > 1) {
            $v = str_replace('.', '', $v);               // 1.234.567
        } elseif (substr_count($v, ',') > 1) {
            $v = str_replace(',', '', $v);               // 1,234,567
        }

        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? $v : null;
    }
}
