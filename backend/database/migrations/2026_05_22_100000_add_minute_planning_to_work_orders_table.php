<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds minute-level planning columns and a composite index used by the
     * hourly view of the schedule planner. Existing weekly / daily / monthly
     * granularities are not affected and continue to operate on shift slots.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->timestamp('planned_start_at')->nullable()->after('end_shift_number');
            $table->timestamp('planned_end_at')->nullable()->after('planned_start_at');
            $table->index(
                ['line_id', 'planned_start_at', 'planned_end_at'],
                'work_orders_line_planning_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('work_orders_line_planning_idx');
            $table->dropColumn(['planned_start_at', 'planned_end_at']);
        });
    }
};
