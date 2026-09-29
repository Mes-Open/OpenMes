<?php

namespace App\Services\Traceability\TestLog;

use InvalidArgumentException;

/**
 * Reads a JSONL file and hands it to the first configured format that
 * recognises it (config/traceability.php). Records that are not JSON objects
 * are skipped, so a stray blank or comment line does not sink a run.
 */
class TestLogParser
{
    /** @var array<int, TestLogFormat> */
    private array $formats;

    /** @param  array<int, class-string<TestLogFormat>|TestLogFormat>|null  $formats */
    public function __construct(?array $formats = null)
    {
        $this->formats = array_map(
            fn ($f) => $f instanceof TestLogFormat ? $f : app($f),
            $formats ?? config('traceability.test_log_formats', []),
        );
    }

    /** @throws InvalidArgumentException when no format recognises the file */
    public function parseFile(string $path): ?TestRun
    {
        return $this->parseString((string) file_get_contents($path));
    }

    /** @throws InvalidArgumentException when no format recognises the content */
    public function parseString(string $jsonl): ?TestRun
    {
        $records = [];
        foreach (preg_split('/\r?\n/', $jsonl) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (is_array($row)) {
                $records[] = $row;
            }
        }

        return $this->parseRecords($records);
    }

    /**
     * Already-decoded records (a file's lines, or an API body) to a run.
     *
     * @param  array<int, array<string, mixed>>  $records
     *
     * @throws InvalidArgumentException when no format recognises them
     */
    public function parseRecords(array $records): ?TestRun
    {
        if ($records === []) {
            return null;
        }

        foreach ($this->formats as $format) {
            if ($format->supports($records[0])) {
                return $format->parse($records);
            }
        }

        throw new InvalidArgumentException('No configured test-log format recognises this file (first record keys: '.implode(', ', array_keys($records[0])).').');
    }
}
