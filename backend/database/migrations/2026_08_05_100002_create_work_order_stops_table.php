<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured production stops on a work order (#182).
 *
 * A stop is the record of *why* production stopped, *what state it was in* at that
 * moment, and *who* resumed it — the thing the plain `IN_PROGRESS → PAUSED` status
 * transition never captured. It never modifies execution data: the produced
 * quantity, the active batch and the snapshot version are copied here as a
 * photograph, not moved.
 *
 * Append-only history, so no soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            // The batch that was running, when the stop is about one.
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();

            // OPERATIONAL | MATERIAL_SHORTAGE | MACHINE_FAILURE | QUALITY_HOLD |
            // ENGINEERING_CHANGE | OTHER — see WorkOrderStopType.
            $table->string('type', 30);
            $table->text('reason');

            // Whether the person stopping expects a configuration change. This is
            // what decides CHANGE_HOLD versus a plain PAUSED, and what makes a
            // change request mandatory before the order can resume.
            $table->boolean('requires_change')->default(false);

            // State photographed at the stop — never read back into execution, only
            // reported. `produced_qty_at_stop` is what makes "35 of 100 were built
            // under the old configuration" answerable months later.
            $table->decimal('produced_qty_at_stop', 12, 2)->default(0);
            $table->unsignedInteger('snapshot_version_at_stop')->nullable();
            $table->json('context')->nullable(); // active/completed batches, steps, allocations

            // Related records. A stop and a downtime are different concepts (why
            // production stopped versus how long a resource was idle), so they are
            // linked, not merged.
            $table->foreignId('production_downtime_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('stopped_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('stopped_at');

            $table->foreignId('resumed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resumed_at')->nullable();
            $table->text('resume_notes')->nullable();
            // Duration is derived on read from stopped_at/resumed_at; the column is
            // the materialised value so downtime reports and OEE can aggregate it
            // without recomputing per row.
            $table->unsignedInteger('duration_minutes')->nullable();

            // The change request that unblocked this stop, when one was required.
            $table->foreignId('applied_change_request_id')->nullable()
                ->constrained('work_order_change_requests')->nullOnDelete();
            // Status the order returned to on resume, for the history line.
            $table->string('resulting_status', 20)->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'stopped_at']);
            // Finding the open stop of an order is the hottest query: resume, the
            // status page and the guard against a second stop all need it.
            $table->index(['work_order_id', 'resumed_at']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_stops');
    }
};
