<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wire change control into the work order and its batches (#182).
 *
 * `work_orders.snapshot_version` says which configuration version the order is
 * currently running; `batches.snapshot_version` says which one a given batch was
 * generated from. That pair is what makes production before and after a change
 * distinguishable — the acceptance criterion the whole feature exists for.
 *
 * Both default to 1 and are backfilled, so every existing order and batch reads as
 * "version 1" rather than as unknown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedInteger('snapshot_version')->default(1)->after('process_snapshot')
                ->comment('Active work_order_snapshots version (#182)');
        });

        Schema::table('batches', function (Blueprint $table) {
            // Defaulted rather than nullable: an insert path that does not set the
            // attribute must still produce a traceable version, and NULL would mean
            // "unknown configuration" for a batch that definitely ran under one.
            $table->unsignedInteger('snapshot_version')->default(1)->after('work_order_id')
                ->comment('Work-order configuration version this batch was generated from (#182)');
        });

        // Existing rows predate change control: they ran under the one and only
        // configuration their order has, which is version 1 by definition. Both
        // columns are added NOT NULL DEFAULT 1, so the backfill is implicit.
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('snapshot_version');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('snapshot_version');
        });
    }
};
