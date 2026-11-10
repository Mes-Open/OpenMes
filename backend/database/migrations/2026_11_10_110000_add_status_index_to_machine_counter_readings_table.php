<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A polled counter writes a reading on every poll — about 86,000 a day per tag
 * at one poll a second — and most say "unchanged". The nightly prune
 * (machine-counters:prune-readings) finds those old rows by status and age.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_counter_readings', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('machine_counter_readings', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
