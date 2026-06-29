<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allow Overproduction
    |--------------------------------------------------------------------------
    |
    | When enabled, allows produced_qty to exceed planned_qty on work orders.
    | When disabled, production stops exactly at planned_qty.
    |
    */
    'allow_overproduction' => env('ALLOW_OVERPRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Force Sequential Steps
    |--------------------------------------------------------------------------
    |
    | When enabled, requires batch steps to be completed in order.
    | Step N+1 cannot be started until Step N is DONE or SKIPPED.
    |
    */
    'force_sequential_steps' => env('FORCE_SEQUENTIAL_STEPS', true),

    /*
    |--------------------------------------------------------------------------
    | Default Token TTL
    |--------------------------------------------------------------------------
    |
    | The default time-to-live for API access tokens in minutes.
    |
    */
    'default_token_ttl_minutes' => env('DEFAULT_TOKEN_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Standard Weekly Hours
    |--------------------------------------------------------------------------
    |
    | Used to convert a weekly-paid worker's salary into an effective hourly
    | rate when attributing labor cost to a work order:
    | effective_hourly = weekly_salary / standard_weekly_hours.
    |
    */
    'standard_weekly_hours' => env('STANDARD_WEEKLY_HOURS', 40),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | System-wide currency. Editable in Settings → System (General). Used as the
    | reporting currency for cost aggregation; there is no FX conversion yet, so
    | per-record currencies are summed numerically and a mixed-currency flag is
    | raised when they differ.
    |
    */
    'default_currency' => env('DEFAULT_CURRENCY', 'PLN'),

    /*
    |--------------------------------------------------------------------------
    | Default Pay Type
    |--------------------------------------------------------------------------
    |
    | Fallback compensation mode (hourly | weekly | piece_rate) used when a
    | worker has no per-worker pay type set. Editable in Settings → System.
    |
    */
    'default_pay_type' => env('DEFAULT_PAY_TYPE', 'hourly'),

    /*
    |--------------------------------------------------------------------------
    | Default Pay Rate
    |--------------------------------------------------------------------------
    |
    | Fallback labor rate used when a worker has no per-worker pay rate (and no
    | wage group). Interpreted according to the effective pay type. Null = no
    | fallback (such workers contribute 0 to labor cost).
    |
    */
    'default_pay_rate' => env('DEFAULT_PAY_RATE'),

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Admin Account
    |--------------------------------------------------------------------------
    |
    | Credentials used to recreate the administrator after a system reset
    | (Settings → System → Reset). Mirrors the ADMIN_* env the Docker entrypoint
    | uses to create the first admin. Read via config() — NOT env() directly —
    | so the values survive `config:cache` in production. The reset refuses to
    | run when any of these is empty (no predictable default account).
    |
    */
    'admin' => [
        'username' => env('ADMIN_USERNAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
