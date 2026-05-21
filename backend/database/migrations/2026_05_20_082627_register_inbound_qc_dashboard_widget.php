<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dashboard_widgets')
            ->where('widget_id', 'inbound_qc_overview')
            ->exists();

        if (! $exists) {
            DB::table('dashboard_widgets')->insert([
                'widget_id' => 'inbound_qc_overview',
                'name' => 'Inbound QC Overview',
                'zone' => 'main',
                'description' => 'Pending inbound inspections and 30-day pass rate',
                'source' => 'builtin',
                'enabled' => true,
                'sort_order' => 25,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('widget_id', 'inbound_qc_overview')->delete();
    }
};
