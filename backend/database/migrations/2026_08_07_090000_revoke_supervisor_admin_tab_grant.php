<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Completes the admin/supervisor split on installs that already exist.
 *
 * Supervisors now have their own `/supervisor` tree and are granted no admin
 * tabs. Changing the seeder is not enough: `RolesAndPermissionsSeeder` keeps
 * whatever `tab:*` grants a role already holds — deliberately, so it can never
 * clobber an admin's choices in Settings → Access — and it runs on every
 * container start. Without this, existing installs would keep re-granting
 * `tab:orders` forever and the split would only ever apply to fresh databases.
 *
 * The seeder can't tell a shipped default from an admin's decision, but a
 * migration that runs once can be careful: `tab:orders` was the *only* tab the
 * seeder ever granted Supervisor, so a Supervisor holding exactly that and
 * nothing else is the untouched default. Any other set means somebody
 * configured this role on purpose, and is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('name', 'Supervisor')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        $tabIds = DB::table('permissions')
            ->where('name', 'like', 'tab:%')
            ->pluck('id', 'name');

        $ordersId = $tabIds['tab:orders'] ?? null;

        $held = DB::table('role_has_permissions')
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $tabIds->values())
            ->pluck('permission_id')
            ->all();

        // Exactly the old default and nothing else — untouched, so safe to
        // retire. Any other set is somebody's deliberate configuration. This
        // also covers tab:orders not existing at all.
        if ($held !== [$ordersId]) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('role_id', $role->id)
            ->where('permission_id', $ordersId)
            ->delete();

        // Spatie caches the permission map outside the database, so a raw-DB
        // change is invisible until it is dropped — the sibling
        // seed_tab_access_permissions migration does the same.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Re-granting would hand supervisors the admin panel again, which is the
        // condition this migration exists to clear. Rolling back the schema
        // should not silently re-open it; an admin can grant the tab in
        // Settings → Access if they want it.
    }
};
