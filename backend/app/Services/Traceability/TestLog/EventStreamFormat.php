<?php

namespace App\Services\Traceability\TestLog;

use Carbon\CarbonImmutable;

/**
 * The event-stream layout: every line is a timestamped `evt`, from `run_start`
 * through `step_start` / `measure` / `fail` / `step_end` to `run_end`.
 *
 *   {"ts":"…","evt":"run_start","station":"…","unit_sn":"…","psn":null,"operator":"…","sw":"…"}
 *   {"ts":"…","evt":"measure","step_id":"T20","char_id":"V_SUPPLY","value":5.02,"unit":"V","low":4.75,"high":5.25,"verdict":"PASS"}
 *   {"ts":"…","evt":"run_end","verdict":"FAIL","fail_count":1,"first_fail_step":"T50","duration_ms":6400}
 *
 * The unit's serial is whichever `*_sn` / `serial` key the run_start carries;
 * measurements keep their limits, since that is what a later reviewer asks.
 */
class EventStreamFormat implements TestLogFormat
{
    public function supports(array $firstRecord): bool
    {
        return ($firstRecord['evt'] ?? null) === 'run_start';
    }

    public function parse(array $records): ?TestRun
    {
        $start = null;
        $end = null;
        $steps = [];
        foreach ($records as $row) {
            switch ($row['evt'] ?? null) {
                case 'run_start':
                    $start = $row;
                    break;
                case 'step_start':
                    $id = (string) ($row['step_id'] ?? '');
                    $steps[$id] = ['id' => $id, 'name' => $row['name'] ?? null, 'verdict' => null, 'duration_ms' => null, 'measurements' => []];
                    break;
                case 'measure':
                    $id = (string) ($row['step_id'] ?? '');
                    $steps[$id] ??= ['id' => $id, 'name' => null, 'verdict' => null, 'duration_ms' => null, 'measurements' => []];
                    $steps[$id]['measurements'][] = array_filter([
                        'name' => $row['char_id'] ?? null,
                        'value' => $row['value'] ?? null,
                        'unit' => $row['unit'] ?? null,
                        'low' => $row['low'] ?? null,
                        'high' => $row['high'] ?? null,
                        'expected' => $row['expected'] ?? null,
                        'verdict' => isset($row['verdict']) ? strtolower((string) $row['verdict']) : null,
                    ], fn ($v) => $v !== null);
                    break;
                case 'step_end':
                    $id = (string) ($row['step_id'] ?? '');
                    $steps[$id] ??= ['id' => $id, 'name' => null, 'verdict' => null, 'duration_ms' => null, 'measurements' => []];
                    $steps[$id]['verdict'] = isset($row['verdict']) ? strtolower((string) $row['verdict']) : null;
                    $steps[$id]['duration_ms'] = isset($row['duration_ms']) ? (int) $row['duration_ms'] : null;
                    break;
                case 'run_end':
                    $end = $row;
                    break;
            }
        }

        $serial = self::serialFrom($start ?? []) ?? self::serialFrom($end ?? []);
        if ($start === null || $serial === null) {
            return null;
        }

        $failed = array_values(array_map(fn ($s) => $s['id'], array_filter($steps, fn ($s) => $s['verdict'] === TestRun::FAIL)));
        $verdict = strtolower((string) ($end['verdict'] ?? ''));

        return new TestRun(
            serialNo: $serial,
            psn: isset($start['psn']) && $start['psn'] !== '' ? (string) $start['psn'] : null,
            runId: $start['run_id'] ?? $start['id'] ?? (isset($start['ts']) ? ($start['station'] ?? '').'@'.$start['ts'] : null),
            verdict: match ($verdict) {
                'fail' => TestRun::FAIL,
                'rework' => TestRun::REWORK,
                'pass' => TestRun::PASS,
                default => $failed === [] ? TestRun::PASS : TestRun::FAIL,
            },
            startedAt: self::time($start['ts'] ?? null),
            endedAt: self::time($end['ts'] ?? null),
            station: $start['station'] ?? null,
            line: $start['line'] ?? null,
            operator: $start['operator'] ?? $start['op'] ?? null,
            steps: array_values($steps),
            failedSteps: $failed,
            extra: array_filter([
                'software' => $start['sw'] ?? null,
                'limits' => $start['limits_map'] ?? null,
                'station_config' => $start['station_cfg'] ?? null,
                'dut' => $start['dut'] ?? null,
                'shift' => $start['shift'] ?? null,
                'first_fail_step' => $end['first_fail_step'] ?? null,
            ], fn ($v) => $v !== null),
        );
    }

    /** The unit's serial under whatever name the tester gives it. */
    private static function serialFrom(array $row): ?string
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && $value !== '' && preg_match('/(^|_)(sn|serial|serial_no)$/i', (string) $key)) {
                return $value;
            }
        }

        return null;
    }

    private static function time(?string $value): ?CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
