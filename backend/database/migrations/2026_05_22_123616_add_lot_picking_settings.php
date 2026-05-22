<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Off by default — tenants opt in to lot tracking.
        // When false, the new code paths are skipped and allocation behaves
        // as before (just quantity, no lot picks).
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'lot_tracking_enabled',
            'value' => json_encode(false),
        ]);

        // fefo | fifo | lifo | manual
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'lot_picking_strategy',
            'value' => json_encode('fefo'),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', ['lot_tracking_enabled', 'lot_picking_strategy'])->delete();
    }
};
