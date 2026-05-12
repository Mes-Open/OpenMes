<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'language',
            'value' => json_encode('en'),
            'description' => 'Application language (en, pl)',
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'language')->delete();
    }
};
