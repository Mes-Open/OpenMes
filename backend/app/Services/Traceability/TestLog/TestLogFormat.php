<?php

namespace App\Services\Traceability\TestLog;

/**
 * One tester's file layout. `supports()` looks at the decoded first record and
 * says whether this parser understands the file; `parse()` turns all of its
 * records into a TestRun, or null when the file holds no complete run.
 */
interface TestLogFormat
{
    /** @param  array<string, mixed>  $firstRecord */
    public function supports(array $firstRecord): bool;

    /** @param  array<int, array<string, mixed>>  $records  decoded JSONL rows, in file order */
    public function parse(array $records): ?TestRun;
}
