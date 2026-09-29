<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Telemetry reports on the software and is on by default; the admin can
     * switch it off here, or before the database exists via OPENMES_TELEMETRY.
     *
     * insertOrIgnore so re-running on an install that already made the choice
     * does not quietly switch it back on.
     */
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'telemetry_enabled',
            'value' => json_encode(true),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'telemetry_enabled',
            'telemetry_last_sent_at',
            'telemetry_last_status',
            'telemetry_retry_after',
            'telemetry_consecutive_failures',
        ])->delete();
    }
};
