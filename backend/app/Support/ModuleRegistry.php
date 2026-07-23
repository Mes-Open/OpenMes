<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Optional feature "modules" (#144) — whole admin areas an installation can
 * switch off to stay lean. Each module key maps 1:1 to a TabRegistry tab; a
 * disabled module hides its tab from navigation AND 404s its routes
 * (TabAccessMiddleware). Core areas (dashboard, orders, production, admin, …)
 * are not listed here and are always on.
 *
 * The enabled set is persisted system-wide in `system_settings.enabled_modules`
 * (a JSON array of keys). When the key is absent every module is treated as
 * enabled, so existing installations and fresh databases are unaffected.
 *
 * This is distinct from the per-role tab-access matrix (who may see a tab) and
 * from the deprecated plugin ModuleManager (`modules/` packages).
 */
class ModuleRegistry
{
    public const SETTING_KEY = 'enabled_modules';

    /** key => [label, description]; key is the TabRegistry tab it controls. */
    public const OPTIONAL = [
        'reports' => [
            'label' => 'Reports',
            'description' => 'Work-order history, production cost, scrap, non-conformance and net-requirements reports.',
        ],
        'structure' => [
            'label' => 'Company structure',
            'description' => 'Sites, areas, factories, divisions, workstation types and subassemblies.',
        ],
        'hr' => [
            'label' => 'HR',
            'description' => 'Workers, crews, skills, wage groups and absences.',
        ],
        'maintenance' => [
            'label' => 'Maintenance & Quality',
            'description' => 'Maintenance events, tools, inspection plans, quality controls and OEE.',
        ],
        'connectivity' => [
            'label' => 'Connectivity',
            'description' => 'Machine connections (MQTT / Modbus / OPC UA) and the live machine monitor.',
        ],
        'packaging' => [
            'label' => 'Packaging',
            'description' => 'Pallets and the packaging station.',
        ],
        'webhooks' => [
            'label' => 'Webhooks',
            'description' => 'Send HTTP notifications to external systems on work-order, issue and batch events.',
        ],
        'erp' => [
            'label' => 'ERP integration',
            'description' => 'REST API to import work orders from an ERP (SAP, Comarch, enova365, Dynamics) and export production and quality data back, secured by API keys.',
        ],
    ];

    /** @return array<int, string> */
    public static function optionalKeys(): array
    {
        return array_keys(self::OPTIONAL);
    }

    /**
     * Enabled optional-module keys. A missing/invalid setting means "all
     * enabled" (back-compat); during install the DB may not exist yet.
     *
     * @return array<int, string>
     */
    public static function enabled(): array
    {
        try {
            $row = DB::table('system_settings')->where('key', self::SETTING_KEY)->first();
        } catch (\Throwable) {
            return self::optionalKeys();
        }

        if (! $row) {
            return self::optionalKeys();
        }

        $vals = json_decode($row->value, true);
        if (! is_array($vals)) {
            return self::optionalKeys();
        }

        return array_values(array_intersect($vals, self::optionalKeys()));
    }

    /** Core (non-optional) areas are always enabled. */
    public static function isModuleEnabled(string $key): bool
    {
        if (! array_key_exists($key, self::OPTIONAL)) {
            return true;
        }

        return in_array($key, self::enabled(), true);
    }

    /** A TabRegistry tab is enabled unless its (optional) module is switched off. */
    public static function isTabEnabled(string $tab): bool
    {
        return self::isModuleEnabled($tab);
    }

    /**
     * Persist the enabled set (invalid/core keys are ignored).
     *
     * @param  array<int, string>  $enabledKeys
     */
    public static function save(array $enabledKeys): void
    {
        $clean = array_values(array_intersect($enabledKeys, self::optionalKeys()));

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($clean), 'updated_at' => now()],
        );
    }

    /**
     * Shape for a selection form: [{key, label, description, enabled}].
     *
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    public static function forForm(): array
    {
        $enabled = self::enabled();

        return array_map(fn (string $k) => [
            'key' => $k,
            'label' => self::OPTIONAL[$k]['label'],
            'description' => self::OPTIONAL[$k]['description'],
            'enabled' => in_array($k, $enabled, true),
        ], self::optionalKeys());
    }
}
