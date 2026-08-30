<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the `orders` tab split from silently taking access away (#182).
 *
 * `orders` used to cover the customer list, the priority rules and the CSV
 * importer as well as the work-order screens. It has been narrowed to the
 * work-order screens so supervisors can run change control without inheriting
 * the commercial pages, which moved to a new `order_data` tab.
 *
 * On a fresh install that is simply the new default. On an existing one, a role
 * an admin granted `tab:orders` to — a planner, say, who opens Customers every
 * day — would keep the grant and lose those three pages with no warning. So
 * every role that holds `tab:orders` today is given `tab:order_data` too: the
 * same reach as before, expressed in the new vocabulary. Narrowing it again is
 * then a deliberate click in Settings → Access.
 *
 * Supervisor is excluded: the seeder grants it `tab:orders` for change control
 * in this same release, and it never had the commercial pages to preserve.
 *
 * This runs before the seeder on boot (docker-entrypoint.sh), so the permission
 * row may not exist yet — hence the firstOrCreate-style insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('permissions')
            ->where('name', 'tab:orders')->where('guard_name', 'web')->value('id');

        if (! $orders) {
            return; // Fresh database — the seeder writes the new defaults.
        }

        $orderData = DB::table('permissions')
            ->where('name', 'tab:order_data')->where('guard_name', 'web')->value('id')
            ?: DB::table('permissions')->insertGetId([
                'name' => 'tab:order_data',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $supervisorId = DB::table('roles')
            ->where('name', 'Supervisor')->where('guard_name', 'web')->value('id');

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $orders)
            ->when($supervisorId, fn ($q) => $q->where('role_id', '!=', $supervisorId))
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $orderData,
            ]);
        }

        // Spatie caches the permission map outside the database, so a raw-DB
        // change is invisible until it is dropped.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Rolling back the schema should not quietly withdraw access an admin
        // has since come to rely on; the tab can be unticked in Settings → Access.
    }
};
