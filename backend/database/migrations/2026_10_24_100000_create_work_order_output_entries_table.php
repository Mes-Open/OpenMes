<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of every change to a work order's `produced_qty`.
 *
 * `produced_qty` is a running total written by several paths (batches, shift
 * entries, machine counters, operator and supervisor reports, corrections),
 * none of which keeps a time-stamped history that covers the others. One row
 * per change makes "how much was produced in this hour" answerable from a
 * single source. Written by WorkOrderOutputObserver; not user-editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_output_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            // The order's line when the output was recorded.
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            // Signed: a correction that lowers produced_qty is negative.
            $table->decimal('delta', 14, 2);
            $table->decimal('produced_total', 14, 2);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['line_id', 'recorded_at']);
            $table->index(['work_order_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_output_entries');
    }
};
