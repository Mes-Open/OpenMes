<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers the non-conformance dashboard widget (#11): open NCRs by type,
 * disposition split and the overdue-actions alert.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dashboard_widgets')
            ->where('widget_id', 'non_conformance_overview')
            ->exists();

        if (! $exists) {
            DB::table('dashboard_widgets')->insert([
                'widget_id' => 'non_conformance_overview',
                'name' => __('Non-conformances'),
                'zone' => 'main',
                'description' => __('Open non-conformances by type, disposition split and overdue actions'),
                'source' => 'builtin',
                'enabled' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('widget_id', 'non_conformance_overview')->delete();
    }
};
