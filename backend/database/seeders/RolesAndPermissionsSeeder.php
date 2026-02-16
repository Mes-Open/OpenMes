<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Work Order permissions
            'view work orders',
            'create work orders',
            'edit work orders',
            'delete work orders',

            // Batch & Step permissions
            'start batch step',
            'complete batch step',
            'skip batch step',

            // Issue permissions
            'view issues',
            'create issues',
            'assign issues',
            'resolve issues',
            'close issues',

            // Line permissions
            'view lines',
            'manage lines',

            // Product & Process permissions
            'view products',
            'manage products',
            'view process templates',
            'manage process templates',

            // CSV Import permissions
            'import csv',
            'view import history',

            // User permissions
            'view users',
            'manage users',

            // Audit permissions
            'view audit logs',
            'view event logs',

            // System permissions
            'manage system settings',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Create roles and assign permissions

        // Admin role - all permissions
        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        $adminRole->givePermissionTo(Permission::all());

        // Supervisor role
        $supervisorRole = Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        $supervisorRole->givePermissionTo([
            'view work orders',
            'create work orders',
            'edit work orders',
            'start batch step',
            'complete batch step',
            'view issues',
            'create issues',
            'assign issues',
            'resolve issues',
            'close issues',
            'view lines',
            'view products',
            'view process templates',
            'import csv',
            'view import history',
            'view users',
            'view audit logs',
            'view event logs',
        ]);

        // Operator role
        $operatorRole = Role::create(['name' => 'Operator', 'guard_name' => 'sanctum']);
        $operatorRole->givePermissionTo([
            'view work orders',
            'start batch step',
            'complete batch step',
            'view issues',
            'create issues',
        ]);
    }
}
