<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dashboard_widgets')
            ->where('widget_id', 'materials_overview')
            ->exists();

        if (! $exists) {
            DB::table('dashboard_widgets')->insert([
                'widget_id' => 'materials_overview',
                'name' => __('Materials Overview'),
                'zone' => 'main',
                'description' => __('Low stock alerts and expiring lots (30-day window)'),
                'source' => 'builtin',
                'enabled' => true,
                'sort_order' => 27,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('widget_id', 'materials_overview')->delete();
    }
};
