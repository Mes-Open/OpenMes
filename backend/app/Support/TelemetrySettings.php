<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Whether this installation reports on itself, and the bookkeeping around it.
 *
 * Every other part of the telemetry stack asks here first. The order of the
 * checks in enabled() matters and is the whole safety design: the env override
 * is answered without touching the database, so a packaged or air-gapped
 * deployment can be born with reporting off and never attempt a query, let
 * alone a request.
 *
 * Nothing here ever throws. A failure to determine the answer is answered
 * "off" — telemetry is the one feature that must never be the reason something
 * else breaks.
 */
class TelemetrySettings
{
    public const SETTING_KEY = 'telemetry_enabled';

    private const CACHE_KEY = 'telemetry.settings.cache';

    /**
     * Short, like ProductionFlow's: long-running workers (the Modbus poller,
     * an Octane worker) never rebuild the container, so a toggle flipped in the
     * UI has to reach them without a restart.
     */
    private const CACHE_SECONDS = 10;

    public static function enabled(): bool
    {
        // 1. Env override. Read first and without a database so it works during
        //    install, in a container built with it off, and on a box that has
        //    no outbound route at all.
        $env = config('telemetry.enabled');
        if ($env !== null && ! self::truthy($env)) {
            return false;
        }
        $envExplicitlyOn = $env !== null && self::truthy($env) && env('OPENMES_TELEMETRY') !== null;

        // 2. Never from a developer machine or the test suite. The explicit env
        //    opt-in exists so a maintainer can still exercise the real path.
        if (app()->environment(['local', 'testing']) && ! $envExplicitlyOn) {
            return false;
        }

        // 3. The demo server's usage is our own marketing traffic, not a signal
        //    about how anybody runs OpenMES in a factory.
        if (config('openmmes.demo_mode')) {
            return false;
        }

        // 4. Mid-install: there is no installation to report on yet.
        if (! self::installed()) {
            return false;
        }

        // 5. The admin's choice. Absent row means never asked, which is off:
        //    silence is not consent. A database we cannot read is not consent
        //    either, so that answers no as well.
        return self::choice() === true;
    }

    /**
     * The stored choice: true/false when known, null when unreadable.
     *
     * Both unknowns answer the same way, for different reasons. "No row" means
     * nobody was ever asked, and an unanswered question is not a yes. "Cannot
     * reach the database" — mid-migration, or the database is down — means we
     * do not know, and reporting on a maybe is not something to do with
     * somebody else's network.
     */
    public static function choice(): ?bool
    {
        try {
            $raw = DB::table('system_settings')->where('key', self::SETTING_KEY)->value('value');
        } catch (\Throwable) {
            return null;
        }

        if ($raw === null) {
            return false;
        }

        return json_decode($raw, true) === true;
    }

    /** Read a telemetry setting, defaulting when the row or the database is absent. */
    public static function setting(string $key, mixed $default = null): mixed
    {
        $cache = self::cache();
        $fresh = microtime(true) - ($cache['at'][$key] ?? 0.0) < self::CACHE_SECONDS;

        if ($fresh && array_key_exists($key, $cache['values'])) {
            return $cache['values'][$key];
        }

        try {
            $raw = DB::table('system_settings')->where('key', $key)->value('value');
        } catch (\Throwable) {
            // No database yet, or it is down. Do not cache a guess.
            return $default;
        }

        $value = $raw === null ? $default : json_decode($raw, true);

        // Read-modify-write: ArrayObject hands back nested arrays by value, so
        // writing through the offset would only edit a copy.
        $values = $cache['values'];
        $values[$key] = $value;
        $cache['values'] = $values;

        $at = $cache['at'];
        $at[$key] = microtime(true);
        $cache['at'] = $at;

        return $value;
    }

    /** Write a telemetry setting. Silent on failure — see the class comment. */
    public static function put(string $key, mixed $value): void
    {
        try {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value)],
            );
        } catch (\Throwable) {
            return;
        }

        self::forget();
    }

    public static function forget(): void
    {
        $cache = self::cache();
        $cache['values'] = [];
        $cache['at'] = [];
    }

    /**
     * The installer's marker. Honours LARAVEL_STORAGE_PATH the same way the
     * rest of the app does, so desktop and unattended installs agree.
     */
    public static function installed(): bool
    {
        try {
            return file_exists(storage_path('installed'));
        } catch (\Throwable) {
            return false;
        }
    }

    private static function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    private static function cache(): \ArrayObject
    {
        $app = app();
        if (! $app->bound(self::CACHE_KEY)) {
            $app->scoped(self::CACHE_KEY, fn () => new \ArrayObject(['values' => [], 'at' => []]));
        }

        return $app->make(self::CACHE_KEY);
    }
}
