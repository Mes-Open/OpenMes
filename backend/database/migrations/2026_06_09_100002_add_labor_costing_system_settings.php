<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Defaults for the labor-costing settings exposed in Settings → System.
        DB::table('system_settings')->insertOrIgnore([
            ['key' => 'standard_weekly_hours', 'value' => json_encode(40), 'description' => 'Standard weekly working hours, used to convert a weekly salary into an hourly cost.'],
            ['key' => 'default_currency', 'value' => json_encode('PLN'), 'description' => 'System-wide currency, used as the reporting currency for production cost aggregation.'],
            ['key' => 'default_pay_type', 'value' => json_encode('hourly'), 'description' => 'Fallback compensation mode (hourly|weekly|piece_rate) when a worker has none.'],
            ['key' => 'default_pay_rate', 'value' => json_encode(null), 'description' => 'Fallback labor rate used when a worker has no per-worker pay rate.'],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', ['standard_weekly_hours', 'default_currency', 'default_pay_type', 'default_pay_rate'])->delete();
    }
};
