<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nameplate rate for a workstation, in pieces per hour. Two things need it and
 * nothing else in the schema can supply it:
 *
 *  - the shift monitor's per-hour target column, and
 *  - "reduced speed" detection, which is not a machine state but a RUNNING
 *    interval producing below its nameplate rate.
 *
 * Nullable: a station without one simply shows no target and never reports a
 * speed loss, rather than guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->decimal('ideal_rate_per_hour', 10, 2)->nullable()->after('workstation_type');
        });
    }

    public function down(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn('ideal_rate_per_hour');
        });
    }
};
