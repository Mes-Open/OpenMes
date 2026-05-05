<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'onboarding_completed',
            'value' => json_encode(false),
            'description' => 'Whether the onboarding wizard has been completed',
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'onboarding_completed')->delete();
    }
};
