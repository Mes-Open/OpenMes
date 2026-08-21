<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment parameters on a process-template step — a free-form key:value recipe
 * (temperature, humidity, pressure, sample size…) that an external client reads
 * via API to drive equipment. Mirrors process_segments.parameters. Nullable and
 * additive; existing steps are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->json('parameters')->nullable()->after('run_time_per_unit_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->dropColumn('parameters');
        });
    }
};
