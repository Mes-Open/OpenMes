<?php

namespace App\Services\Traceability\TestLog;

use Carbon\CarbonImmutable;

/**
 * One test run on one unit, in the shape the importer records - whatever the
 * tester's own file looked like. Formats produce this; nothing downstream
 * knows about their keys.
 */
final class TestRun
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const REWORK = 'rework';

    /**
     * @param  array<int, array{id: string, name: ?string, verdict: ?string, duration_ms: ?int, measurements: array<int, array<string, mixed>>}>  $steps
     * @param  array<int, string>  $failedSteps  ids of the steps that failed
     * @param  array<string, mixed>  $extra  anything else worth keeping (software, limits, fixture…)
     */
    public function __construct(
        public readonly string $serialNo,
        public readonly ?string $psn,
        public readonly ?string $runId,
        public readonly string $verdict,
        public readonly ?CarbonImmutable $startedAt,
        public readonly ?CarbonImmutable $endedAt,
        public readonly ?string $station,
        public readonly ?string $line,
        public readonly ?string $operator,
        public readonly array $steps = [],
        public readonly array $failedSteps = [],
        public readonly array $extra = [],
    ) {}

    /** The moment the history row is stamped with: when the verdict fell, else when the run began. */
    public function processedAt(): ?CarbonImmutable
    {
        return $this->endedAt ?? $this->startedAt;
    }
}
