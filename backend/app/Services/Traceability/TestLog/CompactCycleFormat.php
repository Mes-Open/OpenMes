<?php

namespace App\Services\Traceability\TestLog;

use Carbon\CarbonImmutable;

/**
 * The "one cycle, many short lines" layout: a `cycle` header, one `s` line per
 * command with its result letter, and a closing `v` verdict.
 *
 *   {"t":"cycle","sn":"…","psn":"…","id":"…","start":"2026-03-10T08:15:00.000","station":"TS-01","line":"L1","op":"…","sw":"…"}
 *   {"t":"s","ts":"08:15:03.900","n":7,"cmd":"adc_read","v":2048,"u":"ADC","r":"P"}
 *   {"t":"s","ts":"08:15:13.200","n":11,"cmd":"…","v":41.5,"u":"%","r":"F","lo":50,"hi":90}
 *   {"t":"v","ts":"08:15:20.000","r":"P","fail":[]}
 *
 * Result letters: P pass, F fail, R read/reference, A acknowledged. Only F is
 * a failure; the verdict line's letter decides the run. `lo`/`hi` are the
 * step's limits, kept with its measurement.
 */
class CompactCycleFormat implements TestLogFormat
{
    public function supports(array $firstRecord): bool
    {
        return ($firstRecord['t'] ?? null) === 'cycle';
    }

    public function parse(array $records): ?TestRun
    {
        $cycle = null;
        $steps = [];
        $verdict = null;
        foreach ($records as $row) {
            match ($row['t'] ?? null) {
                'cycle' => $cycle = $row,
                's' => $steps[] = $row,
                'v' => $verdict = $row,
                default => null,
            };
        }
        if ($cycle === null || empty($cycle['sn'])) {
            return null;
        }

        $start = self::time($cycle['start'] ?? null);
        $failedIds = array_map('strval', (array) ($verdict['fail'] ?? []));
        $failedFromSteps = array_map(fn ($s) => (string) ($s['n'] ?? ''), array_filter($steps, fn ($s) => ($s['r'] ?? '') === 'F'));
        // Step ids as strings; "0" is a real step, only empty ones are dropped.
        $failed = array_values(array_unique(array_filter([...$failedIds, ...$failedFromSteps], fn ($id) => $id !== '')));

        return new TestRun(
            serialNo: (string) $cycle['sn'],
            psn: isset($cycle['psn']) && $cycle['psn'] !== '' ? (string) $cycle['psn'] : null,
            // Without an id the run is still identified by unit and start time, so a
            // re-import of the same file does not record it twice.
            runId: isset($cycle['id']) ? (string) $cycle['id'] : ($start ? $cycle['sn'].'@'.$start->toIso8601String() : null),
            // The verdict line's letter decides; without one, the failed steps do.
            verdict: match ($verdict['r'] ?? null) {
                'F' => TestRun::FAIL,
                'R' => TestRun::REWORK,
                'P' => TestRun::PASS,
                default => $failed === [] ? TestRun::PASS : TestRun::FAIL,
            },
            startedAt: $start,
            endedAt: self::timeOfDay($verdict['ts'] ?? null, $start),
            station: $cycle['station'] ?? null,
            line: $cycle['line'] ?? null,
            operator: $cycle['op'] ?? null,
            steps: array_map(fn ($s) => [
                'id' => (string) ($s['n'] ?? ''),
                'name' => $s['cmd'] ?? null,
                'verdict' => match ($s['r'] ?? null) {
                    'F' => TestRun::FAIL, 'P' => TestRun::PASS, default => null
                },
                'duration_ms' => null,
                'measurements' => array_key_exists('v', $s) ? [array_filter([
                    'name' => $s['cmd'] ?? null,
                    'value' => $s['v'],
                    'unit' => $s['u'] ?? null,
                    'low' => $s['lo'] ?? null,
                    'high' => $s['hi'] ?? null,
                ], fn ($v) => $v !== null)] : [],
            ], $steps),
            failedSteps: $failed,
            extra: array_filter([
                'software' => $cycle['sw'] ?? null,
                'limits' => $cycle['lim'] ?? null,
                'fixture' => $cycle['sfr'] ?? null,
                'dut' => $cycle['dut'] ?? null,
            ], fn ($v) => $v !== null),
        );
    }

    private static function time(?string $value): ?CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Step lines carry a clock time only; the date is the cycle's (or the next day's, past midnight). */
    private static function timeOfDay(?string $clock, ?CarbonImmutable $day): ?CarbonImmutable
    {
        if (! $clock || ! $day) {
            return null;
        }
        try {
            $at = CarbonImmutable::parse($day->toDateString().' '.$clock, $day->timezone);
        } catch (\Throwable) {
            return null;
        }

        // A clock time earlier than the start means the run went past midnight.
        return $at->lessThan($day) ? $at->addDay() : $at;
    }
}
