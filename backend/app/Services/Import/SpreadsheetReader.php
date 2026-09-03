<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

/**
 * The one file reader behind the unified importer: CSV (any common delimiter and
 * the encodings Polish ERP exports actually use) and XLS/XLSX, normalised to the
 * same shape — a header list and associative rows keyed by header.
 *
 * Every row carries `_row`, its 1-based line (CSV) or sheet row (spreadsheet)
 * in the source file, so an error can point the user at the line they need to
 * open in Excel rather than at an index that skips blank lines.
 */
class SpreadsheetReader
{
    public const DELIMITERS = ['auto', 'comma', 'semicolon', 'tab'];

    public const ENCODINGS = ['utf-8', 'iso-8859-1', 'windows-1250'];

    public const ROW_KEY = '_row';

    private const DELIMITER_CHARS = ['comma' => ',', 'semicolon' => ';', 'tab' => "\t"];

    /** Option value → the name iconv knows the code page by. */
    private const ICONV_ENCODINGS = ['iso-8859-1' => 'ISO-8859-1', 'windows-1250' => 'CP1250'];

    /**
     * @param  array{delimiter?: string, encoding?: string}  $options
     * @param  int|null  $limit  stop after this many data rows (the preview);
     *                           `total` still counts every non-blank row
     * @return array{headers: list<string>, rows: list<array<string, mixed>>, total: int}
     */
    public function read(string $path, array $options = [], ?int $limit = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Import file not found.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $raw = in_array($ext, ['xlsx', 'xls'], true)
            ? $this->readSpreadsheet($path)
            : $this->readCsv($path, $options['delimiter'] ?? 'auto', $options['encoding'] ?? 'utf-8');

        return $this->normalise($raw, $limit);
    }

    /**
     * Raw cells: [[lineNumber, [cell, cell, …]], …] with the first entry the header line.
     *
     * @return list<array{0: int, 1: list<string>}>
     */
    private function readCsv(string $path, string $delimiter, string $encoding): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Import file could not be read.');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
            $encoding = 'utf-8';
        }

        if (isset(self::ICONV_ENCODINGS[$encoding])) {
            // iconv, not mbstring: the bundled mbstring knows no CP1250.
            $converted = @iconv(self::ICONV_ENCODINGS[$encoding], 'UTF-8//IGNORE', $content);
            $content = $converted === false ? $content : $converted;
        }

        // Invalid UTF-8 (a mis-declared encoding) would break json_encode of the
        // preview and every string function downstream; scrub rather than fail.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        $char = self::DELIMITER_CHARS[$delimiter] ?? $this->detectDelimiter($content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $out = [];
        $line = 0;

        while (($cells = fgetcsv($handle, 0, $char, '"', '\\')) !== false) {
            $line++;

            if ($cells === [null]) {
                continue; // blank line
            }

            $out[] = [$line, array_map(fn ($c) => (string) ($c ?? ''), $cells)];
        }

        fclose($handle);

        return $out;
    }

    /** The most frequent candidate in the header line; comma when nothing matches. */
    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';
        $best = ',';
        $bestCount = -1;

        foreach ([';', ',', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /** @return list<array{0: int, 1: list<string>}> */
    private function readSpreadsheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $out = [];

        // Cell by cell rather than toArray(): a number must arrive as the
        // number, not as its display format ("1,235" for a #,##0 cell), while a
        // date cell must arrive as a date, not as Excel's serial number.
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(false);

            foreach ($iterator as $cell) {
                $cells[] = $this->cellText($cell);
            }

            $out[] = [$row->getRowIndex(), $cells];
        }

        return $out;
    }

    private function cellText(Cell $cell): string
    {
        $value = $cell->getCalculatedValue();

        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value) && Date::isDateTime($cell)) {
            $dt = Date::excelToDateTimeObject((float) $value);

            return (float) $value - floor((float) $value) > 0
                ? $dt->format('Y-m-d H:i:s')
                : $dt->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value) || is_int($value)) {
            // Plain PHP rendering: no thousands separator, no exponent for
            // ordinary quantities, no trailing zeros.
            return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    /**
     * @param  list<array{0: int, 1: list<string>}>  $raw
     * @return array{headers: list<string>, rows: list<array<string, mixed>>, total: int}
     */
    private function normalise(array $raw, ?int $limit): array
    {
        if ($raw === []) {
            return ['headers' => [], 'rows' => [], 'total' => 0];
        }

        [, $headerCells] = array_shift($raw);

        // Blank headers are dropped (Excel pads trailing empty columns); duplicates
        // are suffixed so two "Name" columns do not silently collapse into one.
        $headerMap = [];
        $seen = [];

        foreach ($headerCells as $i => $cell) {
            $header = trim((string) $cell);

            if ($header === '' || $header === self::ROW_KEY) {
                continue;
            }

            if (isset($seen[$header])) {
                $seen[$header]++;
                $header .= ' ('.$seen[$header].')';
            } else {
                $seen[$header] = 1;
            }

            $headerMap[$i] = $header;
        }

        $rows = [];
        $total = 0;

        foreach ($raw as [$line, $cells]) {
            $assoc = [];

            foreach ($headerMap as $i => $header) {
                $assoc[$header] = trim((string) ($cells[$i] ?? ''));
            }

            if (array_filter($assoc, fn ($v) => $v !== '') === []) {
                continue; // trailing blank rows in Excel, empty lines in CSV
            }

            $total++;

            if ($limit !== null && count($rows) >= $limit) {
                continue;
            }

            $assoc[self::ROW_KEY] = $line;
            $rows[] = $assoc;
        }

        return ['headers' => array_values($headerMap), 'rows' => $rows, 'total' => $total];
    }
}
