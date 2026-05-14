<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('widget_id', 50)->unique(); // e.g. 'oee_overview', 'recent_work_orders'
            $table->string('name', 100);
            $table->string('zone', 50)->default('main'); // kpi, main, sidebar
            $table->text('description')->nullable();
            $table->string('source', 20)->default('builtin'); // builtin, module
            $table->string('module_name', 50)->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(50);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        // Seed builtin widgets
        $widgets = [
            ['widget_id' => 'kpi_cards', 'name' => 'KPI Cards', 'zone' => 'kpi', 'description' => 'Work orders, issues, and lines summary cards', 'sort_order' => 10],
            ['widget_id' => 'oee_overview', 'name' => 'OEE Overview', 'zone' => 'main', 'description' => 'Overall Equipment Effectiveness per line (A×P×Q)', 'sort_order' => 20],
            ['widget_id' => 'recent_work_orders', 'name' => 'Recent Work Orders', 'zone' => 'main', 'description' => 'Latest work orders with status and progress', 'sort_order' => 30],
            ['widget_id' => 'open_issues', 'name' => 'Open Issues', 'zone' => 'sidebar', 'description' => 'Currently open issues and problems', 'sort_order' => 40],
            ['widget_id' => 'quick_links', 'name' => 'Quick Links', 'zone' => 'sidebar', 'description' => 'Shortcuts to common admin pages', 'sort_order' => 50],
        ];

        foreach ($widgets as $w) {
            \DB::table('dashboard_widgets')->insert(array_merge($w, [
                'source' => 'builtin',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
