<?php

use App\Support\TabRegistry;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Create the per-tab access permissions (tab:<key>) and grant them to the Admin
 * role on upgrade. Fresh installs get these via RolesAndPermissionsSeeder; this
 * migration covers existing databases. Supervisor/Operator are left untouched
 * (no admin-panel access by default — the matrix UI grants it later).
 */
return new class extends Migration
{
    public function up(): void
    {
        // The roles/permissions tables only exist once the app is installed.
        if (! \Illuminate\Support\Facades\Schema::hasTable('permissions')) {
            return;
        }

        foreach (TabRegistry::permissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo(TabRegistry::permissions());
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('permissions')) {
            return;
        }

        Permission::whereIn('name', TabRegistry::permissions())->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
