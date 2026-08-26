<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The plant timezone, chosen during installation and stored in the database.
 *
 * Every rendered timestamp, report boundary and shift edge is expressed in it —
 * get it wrong and "today's production" covers different hours than the shift the
 * floor actually worked.
 *
 * Why the database and not `APP_TIMEZONE`
 * ---------------------------------------
 * A real environment variable always beats the `.env` file: Laravel bootstraps
 * Dotenv against `Env::getRepository()`, which does not overwrite variables that
 * already exist in the process. `docker-compose.yml` sets `APP_TIMEZONE` on every
 * service (defaulting to UTC), so on a Docker install — the deployment the
 * implementation runbook describes — a value written into `.env` is read and then
 * silently ignored. Storing the choice here is what makes it settable from the
 * installer and, later, changeable without editing compose files or restarting.
 *
 * `APP_TIMEZONE` still works and is still respected: it is the fallback whenever
 * this setting is absent, so existing installs and bare-metal deployments keep
 * behaving exactly as before.
 */
class TimezoneRegistry
{
    public const SETTING_KEY = 'app_timezone';

    /** Cached per process: this is read on every boot, including Octane workers. */
    private static ?string $cached = null;

    /**
     * The configured plant timezone, or null when the installation has not chosen
     * one (fresh install, or the DB is not reachable yet during install).
     */
    public static function stored(): ?string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        try {
            $row = DB::table('system_settings')->where('key', self::SETTING_KEY)->first();
        } catch (\Throwable) {
            // No database yet (installer) or it is mid-migration. The env value
            // stands; this is not an error worth surfacing.
            return null;
        }

        if (! $row || ! self::isValid((string) $row->value)) {
            return null;
        }

        return self::$cached = (string) $row->value;
    }

    /**
     * Apply the stored timezone to the running application.
     *
     * Both halves are needed: `config('app.timezone')` is what the Inertia
     * `timezone` prop and anything reading config sees, and
     * `date_default_timezone_set()` is what Carbon and PHP's date functions use.
     */
    public static function apply(): void
    {
        $timezone = self::stored();

        if ($timezone === null) {
            return;
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }

    /** Persist the choice. Invalid identifiers are refused rather than stored. */
    public static function save(string $timezone): void
    {
        if (! self::isValid($timezone)) {
            throw new \InvalidArgumentException("Unknown timezone identifier: {$timezone}");
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            ['value' => $timezone, 'updated_at' => now()],
        );

        self::$cached = $timezone;
    }

    public static function isValid(string $timezone): bool
    {
        return in_array($timezone, self::identifiers(), true);
    }

    /**
     * Every IANA identifier PHP knows, so a plant anywhere can be configured.
     *
     * @return array<int, string>
     */
    public static function identifiers(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    /**
     * Identifiers grouped by region, for an <optgroup> select.
     *
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::identifiers() as $identifier) {
            // "Europe/Warsaw" -> "Europe"; "UTC" has no region of its own.
            $region = str_contains($identifier, '/') ? explode('/', $identifier)[0] : 'Other';
            $grouped[$region][] = $identifier;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * What the installer should preselect: whatever the application is already
     * running on, so a deployment that set APP_TIMEZONE sees its own value.
     */
    public static function current(): string
    {
        return self::stored() ?? (string) config('app.timezone', 'UTC');
    }

    /**
     * Re-read the setting and apply it, discarding the per-process cache first.
     *
     * `apply()` alone runs in `AppServiceProvider::boot()`, which on Octane (and
     * in a long-lived queue worker) happens once per worker, not once per
     * request — so a zone changed in Settings would keep rendering the old one in
     * every already-booted worker until a restart. Called per request/job, this
     * costs one lookup on a unique key of a tiny table and keeps every worker
     * honest.
     */
    public static function refresh(): void
    {
        self::flush();
        self::apply();
    }

    /** Test seam — the cache is per process and would leak between tests. */
    public static function flush(): void
    {
        self::$cached = null;
    }
}
