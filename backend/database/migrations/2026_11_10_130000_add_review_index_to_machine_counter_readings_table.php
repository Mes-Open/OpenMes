<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The machine counters page counts, for every counter, the readings still
 * waiting for a person (status unassigned/blocked/partial/quality_unknown and
 * not yet reviewed). Without this index that count walks the counter's whole
 * reading history on every page load.
 *
 * Built CONCURRENTLY on PostgreSQL for the same reason as the status index:
 * the collectors write to this table on every poll.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const INDEX = 'machine_counter_readings_counter_status_reviewed_index';

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS '.self::INDEX.' ON machine_counter_readings (machine_counter_id, status, reviewed_at)');

            return;
        }

        Schema::table('machine_counter_readings', function (Blueprint $table) {
            $table->index(['machine_counter_id', 'status', 'reviewed_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.self::INDEX);

            return;
        }

        Schema::table('machine_counter_readings', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }
};
