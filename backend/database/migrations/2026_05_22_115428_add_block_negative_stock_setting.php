<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'block_negative_stock',
            'value' => json_encode(false),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'block_negative_stock')->delete();
    }
};
