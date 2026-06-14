<?php

namespace Database\Seeders;

use App\Support\TabRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Work Orders
            'view work orders', 'create work orders', 'edit work orders', 'delete work orders',

            // Batch & Steps
            'start batch step', 'complete batch step', 'skip batch step',

            // Issues
            'view issues', 'create issues', 'assign issues', 'resolve issues', 'close issues',

            // Lines & Workstations
            'view lines', 'manage lines',

            // Product & Process
            'view products', 'manage products', 'view process templates', 'manage process templates',

            // CSV Import
            'import csv', 'view import history',

            // Users
            'view users', 'manage users',

            // Audit
            'view audit logs', 'view event logs',

            // System
            'manage system settings',

            // Gate 2 — Company structure
            'view factories', 'manage factories',
            'view divisions', 'manage divisions',
            'view workstation types', 'manage workstation types',
            'view subassemblies', 'manage subassemblies',

            // Gate 3 — Basics
            'view companies', 'manage companies',
            'view anomaly reasons', 'manage anomaly reasons',

            // Gate 4 — HR
            'view wage groups', 'manage wage groups',
            'view crews', 'manage crews',
            'view skills', 'manage skills',
            'view workers', 'manage workers',

            // Gate 5 — Tracking advanced
            'view production anomalies', 'manage production anomalies',

            // Gate 6 — Costing
            'view cost sources', 'manage cost sources',

            // Gate 7 — Maintenance
            'view tools', 'manage tools',
            'view maintenance events', 'manage maintenance events',

            // Attachments (cross-cutting)
            'manage attachments',
        ];

        // Per-tab access permissions (tab:<key>) backing the Settings → Access
        // role × tab matrix. Admin gets all of them via syncPermissions below;
        // other roles start with none (they have no admin-panel access today).
        $permissions = array_merge($permissions, TabRegistry::permissions());

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Admin — all permissions
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        // This seeder runs on every container start (docker-entrypoint.sh), so
        // syncPermissions() must not clobber the per-role tab:* grants an admin
        // configured through Settings → Access. Capture them and merge them back
        // into the canonical operational set below. Admin keeps all tabs anyway
        // (Permission::all()); fresh installs have none, so nothing leaks.
        $keepTabs = fn (Role $role) => $role->permissions
            ->pluck('name')
            ->filter(fn ($name) => str_starts_with($name, 'tab:'))
            ->all();

        // Supervisor — operational read + production management
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        $supervisorRole->syncPermissions(array_merge($keepTabs($supervisorRole), [
            'view work orders', 'create work orders', 'edit work orders',
            'start batch step', 'complete batch step',
            'view issues', 'create issues', 'assign issues', 'resolve issues', 'close issues',
            'view lines', 'view products', 'view process templates',
            'import csv', 'view import history',
            'view users', 'view audit logs', 'view event logs',
            // Gate 2
            'view factories', 'view divisions', 'view workstation types', 'view subassemblies',
            // Gate 3
            'view companies', 'view anomaly reasons',
            // Gate 4
            'view wage groups', 'view crews', 'view skills', 'view workers',
            // Gate 5
            'view production anomalies', 'manage production anomalies',
            // Gate 6
            'view cost sources',
            // Gate 7
            'view tools', 'view maintenance events',
        ]));

        // Operator — minimal: view queue + execute steps + report issues
        $operatorRole = Role::firstOrCreate(['name' => 'Operator', 'guard_name' => 'web']);
        $operatorRole->syncPermissions(array_merge($keepTabs($operatorRole), [
            'view work orders',
            'start batch step', 'complete batch step',
            'view issues', 'create issues',
        ]));
    }
}
