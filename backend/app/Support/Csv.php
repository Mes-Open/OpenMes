<?php

namespace App\Support;

class Csv
{
    /**
     * Escape a single cell value for CSV output.
     *
     * Handles two concerns:
     *  - Standard CSV quoting (cells with " , \r \n are wrapped in quotes,
     *    internal quotes doubled).
     *  - Formula-injection prevention: cells starting with =, +, -, @, TAB,
     *    or CR get a leading apostrophe so spreadsheet applications
     *    (Excel, Sheets, LibreOffice) treat them as literal text instead of
     *    executing them as formulas (CWE-1236).
     */
    public static function escape(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Formula injection guard — prepend apostrophe to neutralize.
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'" . $value;
        }

        // Standard CSV quoting
        if (preg_match('/[",\r\n]/', $value)) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * Build a single CSV row from an array of cells.
     * Outputs the line terminated with \r\n (RFC 4180).
     */
    public static function row(array $cells): string
    {
        return implode(',', array_map(fn ($c) => self::escape((string) $c), $cells)) . "\r\n";
    }
}
