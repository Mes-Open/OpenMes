<?php

namespace App\Services\Telemetry;

use App\Support\ModuleRegistry;
use App\Support\TelemetryIdentity;
use App\Support\TelemetrySettings;
use App\Support\TimezoneRegistry;
use Illuminate\Support\Facades\DB;

/**
 * What this installation looks like, described only in terms of the software.
 *
 * Versions, which features are switched on, how big things roughly are, and
 * how recently anything happened. No names, no codes, no numbers that belong
 * to the customer — counts leave as bands (see Buckets) because "50-199 work
 * orders" answers our question while an exact figure would describe a
 * factory's throughput.
 *
 * Nothing here throws. Every section is guarded, and a section that cannot be
 * determined is reported as null rather than taking the whole report down.
 */
class TelemetrySnapshot
{
    /** Tables counted as-is; the key is what the report calls them. */
    private const COUNTS = [
        'users' => 'users',
        'work_orders' => 'work_orders',
        'batches' => 'batches',
        'lines' => 'lines',
        'workstations' => 'workstations',
        'product_types' => 'product_types',
        'process_templates' => 'process_templates',
        'materials' => 'materials',
        'material_lots' => 'material_lots',
        'inspections' => 'inspections',
        'issues' => 'issues',
        'machine_connections' => 'machine_connections',
        'maintenance_events' => 'maintenance_events',
        'webhooks' => 'webhooks',
        'api_keys' => 'api_keys',
    ];

    /** Settings whose value is a mode name rather than a flag. */
    private const MODE_SETTINGS = [
        'production_flow_mode',
        'workflow_mode',
        'realtime_mode',
        'scanner_mode',
    ];

    private const FLAG_SETTINGS = [
        'pin_login_enabled',
        'allow_registration',
        'block_negative_stock',
    ];

    public function build(): array
    {
        return [
            'install_id' => TelemetryIdentity::installId(),
            'schema_version' => (int) config('telemetry.schema_version', 1),
            'sent_at' => now()->utc()->toIso8601String(),
            'app' => $this->guard(fn () => $this->app()),
            'runtime' => $this->guard(fn () => $this->runtime()),
            'features' => $this->guard(fn () => $this->features()),
            'usage' => $this->guard(fn () => $this->usage()),
            'liveness' => $this->guard(fn () => $this->liveness()),
            'errors' => $this->guard(fn () => TelemetryErrorBuffer::collect()),
        ];
    }

    private function app(): array
    {
        return [
            'version' => config('version.current'),
            'locale' => app()->getLocale(),
            'timezone' => TimezoneRegistry::current(),
            'environment' => app()->environment(),
            'demo_mode' => (bool) config('openmmes.demo_mode'),
            // Age, not a date. When an installation was stood up is a fact
            // about the customer's project; how long it has been running is a
            // fact about our software's lifecycle.
            'installed_days' => $this->installedDays(),
        ];
    }

    private function runtime(): array
    {
        return [
            // Major.minor only. A full version string can carry a build host
            // name, and the patch level is not a question we ask.
            'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'laravel' => app()->version(),
            'os_family' => PHP_OS_FAMILY,
            'arch' => php_uname('m'),
            'docker' => file_exists('/.dockerenv'),
            'octane' => class_exists(\Laravel\Octane\Events\RequestReceived::class),
            'queue_driver' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'broadcast_driver' => config('broadcasting.default'),
            'db_driver' => $this->guard(fn () => DB::connection()->getDriverName()),
            'db_version' => $this->guard(fn () => $this->databaseVersion()),
        ];
    }

    private function features(): array
    {
        $settings = $this->settings();

        $features = [
            // Which parts of OpenMES this site actually switched on — the
            // clearest answer we have to "what should we build next".
            'modules' => $this->guard(fn () => array_values(ModuleRegistry::enabled())) ?? [],
            // Names only. A plugin's version or path is not ours to collect.
            'plugins' => $this->guard(fn () => array_values(app(\App\Services\ModuleManager::class)->enabledNames())) ?? [],
        ];

        // The effective mode, not the stored one. An installation that never
        // touched the setting is still running a mode, and "whole_batch by
        // default" is the same answer as "whole_batch by choice" to the only
        // question we are asking.
        $features['production_flow_mode'] = $this->guard(fn () => \App\Support\ProductionFlow::mode());

        foreach (self::MODE_SETTINGS as $key) {
            if ($key === 'production_flow_mode') {
                continue;
            }

            $value = $settings[$key] ?? null;
            // null here means the shipped default was never changed — which is
            // itself worth knowing.
            $features[$key] = is_scalar($value) ? (string) $value : null;
        }

        foreach (self::FLAG_SETTINGS as $key) {
            $features[$key] = (bool) ($settings[$key] ?? false);
        }

        return $features;
    }

    private function usage(): array
    {
        $usage = [];

        foreach (self::COUNTS as $label => $table) {
            $usage[$label] = Buckets::of($this->count($table));
        }

        // Which industrial protocols are actually in the field. This is the
        // difference between guessing and knowing whether OPC UA is worth the
        // maintenance it costs us.
        $usage['machine_connections_by_protocol'] = [
            'mqtt' => Buckets::of($this->count('mqtt_connections')),
            'modbus' => Buckets::of($this->count('modbus_connections')),
            'opcua' => Buckets::of($this->count('opcua_connections')),
        ];

        return $usage;
    }

    private function liveness(): array
    {
        return [
            // Separates "installed and running a plant" from "installed once
            // and abandoned" without asking what was produced.
            'last_activity_days' => $this->guard(fn () => $this->daysSinceLastActivity()),
        ];
    }

    /**
     * Counts go through the query builder rather than Eloquent on purpose.
     *
     * TenantScope is a no-op without an authenticated user, so a model count
     * inside a console command already spans every tenant — using DB::table
     * makes that explicit instead of resting on the scope staying that way.
     */
    private function count(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            // The table belongs to a module this install does not have, or the
            // schema predates it. Absent is zero.
            return 0;
        }
    }

    private function installedDays(): ?int
    {
        try {
            $path = storage_path('installed');
            if (! is_file($path)) {
                return null;
            }

            $days = (int) floor((time() - filemtime($path)) / 86400);

            return max(0, min($days, 3650));
        } catch (\Throwable) {
            return null;
        }
    }

    private function daysSinceLastActivity(): ?int
    {
        $latest = DB::table('batch_steps')->max('updated_at');

        if ($latest === null) {
            return null;
        }

        $days = (int) floor((time() - strtotime((string) $latest)) / 86400);

        return max(0, min($days, 365));
    }

    /** Major.minor of the database server — enough to plan support windows. */
    private function databaseVersion(): ?string
    {
        $driver = DB::connection()->getDriverName();

        $raw = match ($driver) {
            'pgsql' => DB::selectOne('show server_version')->server_version ?? null,
            'mysql', 'mariadb' => DB::selectOne('select version() as v')->v ?? null,
            'sqlite' => DB::selectOne('select sqlite_version() as v')->v ?? null,
            default => null,
        };

        if (! is_string($raw)) {
            return null;
        }

        return preg_match('/^(\d+)(?:\.(\d+))?/', $raw, $m)
            ? $m[1].'.'.($m[2] ?? '0')
            : null;
    }

    /** Every system setting in one read rather than a query per key. */
    private function settings(): array
    {
        try {
            return DB::table('system_settings')
                ->pluck('value', 'key')
                ->map(fn ($raw) => json_decode((string) $raw, true))
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @template T */
    private function guard(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    /** True when this installation may report at all. */
    public static function permitted(): bool
    {
        return TelemetrySettings::enabled() && TelemetryIdentity::installId() !== null;
    }
}
