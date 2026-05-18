<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'cors_allowed_origins',
            'value' => json_encode('*'),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'cors_allowed_origins')->delete();
    }
};
