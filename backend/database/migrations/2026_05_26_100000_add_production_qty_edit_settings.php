<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            ['key' => 'production_qty_edit_policy', 'value' => json_encode('none')],
            ['key' => 'production_qty_edit_window_minutes', 'value' => json_encode(1)],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('key', ['production_qty_edit_policy', 'production_qty_edit_window_minutes'])
            ->delete();
    }
};
