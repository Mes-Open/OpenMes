<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'realtime_mode',
            'value' => json_encode('polling'),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'realtime_mode')->delete();
    }
};
