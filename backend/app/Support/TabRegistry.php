<?php

namespace App\Support;

/**
 * Canonical list of admin-panel "tabs" — the unit of access the role × tab
 * matrix (Settings → Access) grants. Each tab maps to a set of `/admin` path
 * prefixes so a single middleware can gate every sub-route without touching the
 * individual route groups. This is the one source of truth shared by the
 * backend middleware, the Inertia nav filter and the matrix UI.
 */
class TabRegistry
{
    /**
     * tab key => [label, path prefixes]. Order drives the matrix row order.
     *
     * @var array<string, array{label: string, prefixes: array<int, string>}>
     */
    private const TABS = [
        'dashboard' => ['label' => 'Dashboard', 'prefixes' => ['/admin/dashboard']],
        'alerts' => ['label' => 'Alerts', 'prefixes' => ['/admin/alerts']],
        'schedule' => ['label' => 'Schedule', 'prefixes' => ['/admin/schedule']],
        'orders' => ['label' => 'Orders', 'prefixes' => ['/admin/work-orders', '/admin/csv-import']],
        'production' => ['label' => 'Production', 'prefixes' => [
            '/admin/product-types', '/admin/materials', '/admin/material-lots', '/admin/traceability',
            '/admin/lot-sequences', '/admin/process-segments', '/admin/lines', '/admin/line-statuses',
            '/admin/view-templates', '/admin/shifts', '/admin/issues', '/admin/companies',
            '/admin/anomaly-reasons', '/admin/scrap-reasons',
        ]],
        'reports' => ['label' => 'Reports', 'prefixes' => ['/admin/reports', '/admin/cost-reports', '/admin/scrap-reports', '/admin/non-conformance-reports', '/admin/net-requirements']],
        'structure' => ['label' => 'Structure', 'prefixes' => [
            '/admin/sites', '/admin/areas', '/admin/factories', '/admin/divisions',
            '/admin/workstation-types', '/admin/subassemblies',
        ]],
        'hr' => ['label' => 'HR', 'prefixes' => [
            '/admin/workers', '/admin/worker-absences', '/admin/personnel-classes', '/admin/crews',
            '/admin/crew-break-windows', '/admin/skills', '/admin/wage-groups',
        ]],
        'maintenance' => ['label' => 'Maintenance', 'prefixes' => [
            '/admin/maintenance-events', '/admin/maintenance-schedules', '/admin/tools', '/admin/cost-sources',
            '/admin/production-anomalies', '/admin/inspection-plans', '/admin/quality-control-triggers',
            '/admin/quality-tasks', '/admin/oee',
        ]],
        'connectivity' => ['label' => 'Connectivity', 'prefixes' => ['/admin/connectivity', '/admin/machine-monitor']],
        'webhooks' => ['label' => 'Webhooks', 'prefixes' => ['/admin/webhooks']],
        'admin' => ['label' => 'Admin', 'prefixes' => ['/admin/users', '/admin/logs', '/admin/audit-logs', '/admin/trash']],
        'modules' => ['label' => 'Modules', 'prefixes' => ['/admin/modules']],
        'packaging' => ['label' => 'Packaging', 'prefixes' => ['/admin/pallets']],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::TABS);
    }

    /**
     * Tab keys the given user may access — granted by role AND with the feature
     * module enabled for this install (#144). The single source of truth shared
     * by the Inertia nav filter (HandleInertiaRequests) and the mobile API
     * (AuthController) so web and mobile nav filtering can't drift.
     *
     * @return array<int, string>
     */
    public static function accessibleFor($user): array
    {
        if (! $user) {
            return [];
        }

        return array_values(array_filter(
            self::keys(),
            fn (string $key) => $user->can(self::permission($key))
                && ModuleRegistry::isTabEnabled($key),
        ));
    }

    /** tab key => label, for the matrix rows. @return array<string, string> */
    public static function labels(): array
    {
        return array_map(fn ($t) => $t['label'], self::TABS);
    }

    /** The primary landing path for a tab (its first prefix), or null. */
    public static function url(string $key): ?string
    {
        return self::TABS[$key]['prefixes'][0] ?? null;
    }

    /** The Spatie permission name backing a tab. */
    public static function permission(string $key): string
    {
        return "tab:{$key}";
    }

    /** @return array<int, string> every tab permission name */
    public static function permissions(): array
    {
        return array_map(fn ($k) => self::permission($k), self::keys());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::TABS);
    }

    /**
     * Resolve the tab a request path belongs to, or null if the path isn't
     * mapped (an admin route outside the matrix). Path is matched with or
     * without a leading slash.
     */
    public static function tabForPath(string $path): ?string
    {
        $path = '/'.ltrim($path, '/');

        foreach (self::TABS as $key => $tab) {
            foreach ($tab['prefixes'] as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * The landing path for a user granted admin-panel tabs — the first tab (in
     * registry order) they can access. Lets a non-admin with granted tabs enter
     * the admin panel (and see the sidebar) instead of their default screen.
     * Returns null when the user has no accessible tab.
     */
    public static function firstAccessibleUrl($user): ?string
    {
        if (! $user) {
            return null;
        }

        foreach (self::TABS as $key => $tab) {
            if ($user->can(self::permission($key))) {
                return $tab['prefixes'][0] ?? null;
            }
        }

        return null;
    }
}
