<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_sequences', function (Blueprint $table) {
            // Token-based LOT template, e.g. "test-[date]-[seq]-[hour]".
            // NULL = legacy mode (prefix / year_prefix / pad_size / suffix).
            $table->string('pattern', 100)->nullable()->after('suffix');

            // Restart the counter at period boundaries: none|yearly|monthly|daily|hourly.
            $table->string('reset_period', 10)->default('none')->after('pad_size');

            // Period key of the last generation (e.g. "2026-06-06" for daily) —
            // when it differs from the current period the counter resets to 1.
            $table->string('last_reset_key', 20)->nullable()->after('reset_period');
        });
    }

    public function down(): void
    {
        Schema::table('lot_sequences', function (Blueprint $table) {
            $table->dropColumn(['pattern', 'reset_period', 'last_reset_key']);
        });
    }
};
