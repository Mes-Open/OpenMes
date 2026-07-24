<?php

use App\Support\ModuleRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The optional-module catalog was split into finer feature toggles (Advanced
 * reports, Materials & tracing, Product engineering, Companies, Issues &
 * reasons). On the previous release those areas were either core (always on,
 * under the Production nav group) or bundled into the Reports tab. An install
 * that had *explicitly* chosen a module subset would otherwise lose them on
 * upgrade, so expand any saved set to keep the previously-visible areas on.
 * Installs with no saved set default to all-on and need no change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $row = DB::table('system_settings')->where('key', ModuleRegistry::SETTING_KEY)->first();
        if (! $row) {
            return; // unset → every module on, nothing to migrate
        }

        $set = json_decode($row->value, true);
        if (! is_array($set) || $set === []) {
            return;
        }

        // Formerly-core areas (Production nav group) — keep them visible.
        $set = array_merge($set, ['materials', 'product_engineering', 'companies', 'quality']);

        // Cost / scrap / non-conformance / net-requirements used to live on the Reports tab.
        if (in_array('reports', $set, true)) {
            $set[] = 'advanced_reports';
        }

        $clean = array_values(array_intersect(array_unique($set), ModuleRegistry::optionalKeys()));

        DB::table('system_settings')
            ->where('key', ModuleRegistry::SETTING_KEY)
            ->update(['value' => json_encode($clean), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // One-way data backfill; the expanded set is harmless to leave in place.
    }
};
