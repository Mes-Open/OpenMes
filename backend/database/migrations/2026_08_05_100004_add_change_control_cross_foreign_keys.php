<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Close the two cross-references change control could not constrain at create time (#182).
 *
 * `work_order_snapshots`, `work_order_change_requests` and `work_order_stops` point at
 * each other in a cycle, so whichever table is created first cannot constrain the one
 * created after it. Both columns were therefore declared as bare `foreignId()`:
 *
 *   - `work_order_snapshots.change_request_id` → `work_order_change_requests` (created later)
 *   - `work_order_change_requests.work_order_stop_id` → `work_order_stops` (created later)
 *
 * All three tables exist by the time this runs, so the constraints go on here rather
 * than by reordering — renaming a migration is never an option once it has shipped.
 *
 * `nullOnDelete` in both directions: deleting the record on the other side removes the
 * cross-reference, it never cascades into deleting history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_snapshots', function (Blueprint $table) {
            $table->foreign('change_request_id')
                ->references('id')->on('work_order_change_requests')
                ->nullOnDelete();
        });

        Schema::table('work_order_change_requests', function (Blueprint $table) {
            $table->foreign('work_order_stop_id')
                ->references('id')->on('work_order_stops')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_order_snapshots', function (Blueprint $table) {
            $table->dropForeign(['change_request_id']);
        });

        Schema::table('work_order_change_requests', function (Blueprint $table) {
            $table->dropForeign(['work_order_stop_id']);
        });
    }
};
