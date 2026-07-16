<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Register the "Top customers" admin-dashboard widget (revenue leaderboard).
 * Fed by DashboardController::topCustomers from the customer aggregate metrics.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dashboard_widgets')
            ->where('widget_id', 'top_customers')
            ->exists();

        if (! $exists) {
            DB::table('dashboard_widgets')->insert([
                'widget_id' => 'top_customers',
                'name' => __('Top Customers'),
                'zone' => 'main',
                'description' => __('Highest-revenue customers and total revenue'),
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
        DB::table('dashboard_widgets')->where('widget_id', 'top_customers')->delete();
    }
};
