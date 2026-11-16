<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Machine counters (Admin → Connectivity → Machine counters) move to their own
 * `machine_counters` tab, so a supervisor can be given them in Settings →
 * Access without the broker, PLC and gateway configuration.
 *
 * Every role that could open them before — anyone holding `connectivity` —
 * keeps them. Nobody else gets them by default.
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

        $connectivity = DB::table('permissions')
            ->where('name', 'tab:connectivity')->where('guard_name', 'web')->value('id');

        if (! $connectivity) {
            return; // Fresh database — the seeder creates the permission.
        }

        $counters = DB::table('permissions')
            ->where('name', 'tab:machine_counters')->where('guard_name', 'web')->value('id')
            ?: DB::table('permissions')->insertGetId([
                'name' => 'tab:machine_counters',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $roleIds = DB::table('role_has_permissions')->where('permission_id', $connectivity)->pluck('role_id');
        $adminId = DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->value('id');

        DB::table('role_has_permissions')->insertOrIgnore(
            $roleIds->push($adminId)->filter()->unique()
                ->map(fn ($roleId) => ['role_id' => $roleId, 'permission_id' => $counters])
                ->values()->all()
        );

        $userIds = DB::table('model_has_permissions')->where('permission_id', $connectivity)->get(['model_type', 'model_id']);
        DB::table('model_has_permissions')->insertOrIgnore(
            $userIds->map(fn ($row) => ['permission_id' => $counters, 'model_type' => $row->model_type, 'model_id' => $row->model_id])->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Access an admin has come to rely on is not withdrawn by a rollback;
        // the tab can be unticked in Settings → Access.
    }
};
