<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned work-order configuration snapshots (#182).
 *
 * `work_orders.process_snapshot` holds the ACTIVE configuration and stays exactly
 * where it is — batches read it when their steps are generated, so it must keep
 * working untouched. This table adds the history around it: every configuration a
 * work order has ever run under, in order, each with the point from which it took
 * effect.
 *
 * Append-only by design. A snapshot is a record of what the shop floor was told to
 * build, so it is never edited and never deleted — which is also why there are no
 * soft deletes here: there is no user-facing delete to hide a row from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();

            // 1 for the configuration the order was created with, then 2, 3, … for
            // each applied change. Quoted in history and on batches.
            $table->unsignedInteger('version');

            // The frozen configuration: steps, merged BOM, the revision block (#180)
            // and the released engineering documents (#179) — the same shape as
            // work_orders.process_snapshot.
            $table->json('snapshot');

            // From where this configuration applies — see ChangeEffectivePoint.
            $table->string('effective_from', 30)->default('IMMEDIATE');
            // Set for REMAINING_QUANTITY: produced quantity at the moment of the
            // switch, so "units 1–35 ran under v1, 36–100 under v2" is answerable.
            $table->decimal('effective_from_qty', 12, 2)->nullable();
            // Set for NEXT_BATCH: the first batch that must use this version.
            $table->foreignId('effective_from_batch_id')->nullable()->constrained('batches')->nullOnDelete();

            // Null on version 1 (creation); set on every version an applied change
            // produced, so a snapshot always answers "why does this exist".
            $table->foreignId('change_request_id')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'version']);
        });

        // One row per (order, version). Not a plain unique() because the version is
        // computed from the current maximum, and two concurrent applies would
        // otherwise both write version 3.
        DB::statement(
            'CREATE UNIQUE INDEX work_order_snapshots_version_unique
             ON work_order_snapshots (work_order_id, version)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_snapshots');
    }
};
