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
];
