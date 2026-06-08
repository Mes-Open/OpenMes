<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISA-95 genealogy — junction recording which lots (and optional sublots) were
 * consumed by which batch step. Powers forward / backward traceability:
 *
 *   forward  (lot → ?)  : "Lot X went into batches A, B, C"
 *                         SELECT ... FROM batch_step_lot_consumption WHERE material_lot_id = X
 *   backward (batch → ?): "Batch Y was made from lots P, Q, R"
 *                         SELECT ... WHERE batch_step_id IN (Y.steps)
 *
 * Material consumption remains optional — existing flows that don't record
 * lot usage continue to work without writing rows here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_step_lot_consumption', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_step_id')->constrained()->cascadeOnDelete();

            // Use restrictOnDelete: lots referenced by historical consumptions must
            // not be silently removed — preserves the audit trail.
            $table->foreignId('material_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('sublot_id')->nullable()->constrained('material_sublots')->nullOnDelete();

            $table->decimal('quantity_consumed', 14, 4);
            $table->timestamp('consumed_at')->useCurrent();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['batch_step_id']);
            $table->index(['material_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_step_lot_consumption');
    }
};
