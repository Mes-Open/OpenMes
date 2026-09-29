<?php

namespace App\Services\Traceability\TestLog;

use Carbon\CarbonImmutable;

/**
 * OpenMES's own test-run layout: one JSON object with the fields the importer
 * records, for a tester that talks to the API directly rather than writing a
 * file in its own format.
 *
 *   {"serial_no":"…","psn":"…","run_id":"…",
 *    "verdict":"pass|fail|rework","started_at":"<ISO 8601>","ended_at":"<ISO 8601>",
 *    "station":"<workstation code>","line":"<line code>","operator":"<name or username>",
 *    "steps":[{"id":"…","name":"…","verdict":"pass|fail","duration_ms":0,
 *              "measurements":[{"name":"…","value":0,"unit":"…","low":0,"high":0}]}],
 *    "failed_steps":["…"],"extra":{"<key>":"<value>"}}
 *
 * `verdict` is pass | fail | rework; with no verdict, a run with a failed step
 * fails and any other passes. Everything but `serial_no` is optional.
 */
class NativeTestRunFormat implements TestLogFormat
{
    public function supports(array $firstRecord): bool
    {
        return isset($firstRecord['serial_no']) && ! isset($firstRecord['t']) && ! isset($firstRecord['evt']);
    }

    public function parse(array $records): ?TestRun
    {
        $row = $records[0] ?? null;
        if (! is_array($row) || trim((string) ($row['serial_no'] ?? '')) === '') {
            return null;
        }

        $steps = array_values(array_map(fn ($s) => [
            'id' => (string) ($s['id'] ?? $s['n'] ?? ''),
            'name' => isset($s['name']) ? (string) $s['name'] : null,
            'verdict' => self::verdict($s['verdict'] ?? null),
            'duration_ms' => isset($s['duration_ms']) ? (int) $s['duration_ms'] : null,
            'measurements' => array_values(array_filter((array) ($s['measurements'] ?? []), 'is_array')),
        ], array_filter((array) ($row['steps'] ?? []), 'is_array')));

        $failed = array_values(array_unique(array_map('strval', array_merge(
            (array) ($row['failed_steps'] ?? []),
            array_column(array_filter($steps, fn ($s) => $s['verdict'] === TestRun::FAIL), 'id'),
        ))));

        return new TestRun(
            serialNo: trim((string) $row['serial_no']),
            psn: isset($row['psn']) && trim((string) $row['psn']) !== '' ? trim((string) $row['psn']) : null,
            runId: isset($row['run_id']) && (string) $row['run_id'] !== '' ? (string) $row['run_id'] : null,
            verdict: self::verdict($row['verdict'] ?? null) ?? ($failed === [] ? TestRun::PASS : TestRun::FAIL),
            startedAt: self::time($row['started_at'] ?? null),
            endedAt: self::time($row['ended_at'] ?? null),
            station: isset($row['station']) ? (string) $row['station'] : null,
            line: isset($row['line']) ? (string) $row['line'] : null,
            operator: isset($row['operator']) ? (string) $row['operator'] : null,
            steps: $steps,
            failedSteps: $failed,
            extra: array_filter((array) ($row['extra'] ?? []), fn ($v) => $v !== null && $v !== ''),
        );
    }

    private static function verdict(mixed $value): ?string
    {
        return match (strtolower((string) $value)) {
            'pass', 'p', 'ok' => TestRun::PASS,
            'fail', 'f', 'nok' => TestRun::FAIL,
            'rework', 'r' => TestRun::REWORK,
            default => null,
        };
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse((string) $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
