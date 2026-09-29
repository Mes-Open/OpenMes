<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Test-station log formats
    |--------------------------------------------------------------------------
    |
    | Parsers tried, in order, against each imported JSONL file; the first whose
    | `supports()` recognises the file's opening record reads it. The same list
    | serves POST /api/v1/test-runs, so a tester posts the log it would have
    | written. Each entry implements App\Services\Traceability\TestLog\TestLogFormat.
    | A plant with its own tester adds its class here (or in a module) rather
    | than in core; the native layout is OpenMES's own JSON shape.
    |
    */

    'test_log_formats' => [
        App\Services\Traceability\TestLog\NativeTestRunFormat::class,
        App\Services\Traceability\TestLog\CompactCycleFormat::class,
        App\Services\Traceability\TestLog\EventStreamFormat::class,
    ],

];
