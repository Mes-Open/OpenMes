<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The unified importer lives on its own `import` tab (Admin → Import). It loads
 * master data and can replace recipes, so it is not handed to every role that
 * held the old work-order CSV importer (`order_data`): the permission row is
 * created and granted to Admin only, and other roles get it deliberately in
 * Settings → Access.
 *
 * Runs before the seeder on boot (docker-entrypoint.sh), so the permission row
 * may not exist yet — hence the insert-if-missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        $orderData = DB::table('permissions')
            ->where('name', 'tab:order_data')->where('guard_name', 'web')->value('id');

        if (! $orderData) {
            return; // Fresh database — the seeder writes the new defaults.
        }

        $import = DB::table('permissions')
            ->where('name', 'tab:import')->where('guard_name', 'web')->value('id')
            ?: DB::table('permissions')->insertGetId([
                'name' => 'tab:import',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $adminId = DB::table('roles')
            ->where('name', 'Admin')->where('guard_name', 'web')->value('id');

        if ($adminId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $adminId,
                'permission_id' => $import,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Access an admin has come to rely on is not withdrawn by a rollback;
        // the tab can be unticked in Settings → Access.
    }
};
