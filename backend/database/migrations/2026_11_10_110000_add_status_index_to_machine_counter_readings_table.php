<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A polled counter writes a reading on every poll — about 86,000 a day per tag
 * at one poll a second — and most say "unchanged". The nightly prune
 * (machine-counters:prune-readings) finds those old rows by status and age.
 *
 * On PostgreSQL the index is built CONCURRENTLY: this table takes a write on
 * every poll, and a plain CREATE INDEX would hold those writes (and the
 * collectors) for as long as the build of a large table takes. That cannot
 * run inside a transaction, hence $withinTransaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const INDEX = 'machine_counter_readings_status_created_at_index';

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS '.self::INDEX.' ON machine_counter_readings (status, created_at)');

            return;
        }

        Schema::table('machine_counter_readings', function (Blueprint $table) {
            $table->index(['status', 'created_at'], self::INDEX);
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
