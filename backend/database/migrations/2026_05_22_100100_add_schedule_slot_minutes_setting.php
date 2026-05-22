<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Seeds the `schedule_slot_minutes` system setting which controls the
     * snap granularity (in minutes) for the hourly view of the schedule
     * planner. Default is 15 minutes; supported values are 5, 10, 15, 30, 60.
     */
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'schedule_slot_minutes'],
            [
                'value' => '"15"',
                'description' => 'Snap granularity in minutes for the hourly schedule view (5, 10, 15, 30 or 60).',
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')->where('key', 'schedule_slot_minutes')->delete();
    }
};
