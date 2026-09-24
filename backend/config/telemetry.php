<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telemetry
    |--------------------------------------------------------------------------
    |
    | OpenMES reports on ITSELF — versions, which features are switched on,
    | rough size bands, and where errors occur (class, file, line). It never
    | reports anything entered into OpenMES: no material or product codes, no
    | lot numbers, no order data, no recipes, no personal data, and no error
    | message text. That boundary is enforced by tests, not by convention —
    | see tests/Feature/Telemetry/.
    |
    | Reporting is OFF until an administrator turns it on in Settings. It used
    | to be on unless switched off; opt-in is the honest default when the data
    | leaves somebody else's network, and in the EU it is also the position
    | that does not depend on defending a legitimate interest.
    |
    | This env var still overrides the setting in both directions and is read
    | before the database exists, so a packaged or air-gapped deployment can be
    | born with it off — and a maintainer can force it on without a database.
    |
    */

    'enabled' => env('OPENMES_TELEMETRY'),

    'endpoint' => env('TELEMETRY_URL', 'https://getopenmes.com/telemetry.php'),

    // Bumped only when the payload shape changes in a way the receiver must
    // notice. The receiver rejects versions it does not know.
    'schema_version' => 1,

    // Where the error buffer lives. Deliberately the cache rather than a table:
    // errors spike exactly when the database is in trouble, and a buffer that
    // needs a healthy database to record a database failure is no buffer.
    'buffer_store' => env('TELEMETRY_BUFFER_STORE'),

    // Distinct error fingerprints kept per window. Beyond this only a count of
    // what was dropped is carried, so a storm cannot inflate the payload.
    'max_fingerprints' => 50,

    'buffer_ttl_hours' => 48,
];
