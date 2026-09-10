<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-(serial unit, step) progression gate (#290) for batches whose template
 * runs in 'unit' execution_mode. Deliberately parallel to, not a replacement
 * for, batch_steps: batch_steps stays the batch-wide record that material
 * consumption, quality checks and typed outputs book against (unchanged for
 * every batch); unit_steps only decides what an individual piece may do next,
 * so pieces in the same batch can sit at different steps simultaneously.
 *
 * Status vocabulary intentionally mirrors BatchStep's (PENDING/READY/
 * IN_PROGRESS/DONE/SKIPPED) but is its own set of constants on the UnitStep
 * model, not shared via a trait — see scope doc decision #3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serial_unit_id')->constrained()->cascadeOnDelete();
            // The aggregate BatchStep this row tracks progress against (for its
            // name/instruction/workstation) — same batch, same step_number.
            $table->foreignId('batch_step_id')->constrained()->cascadeOnDelete();
            $table->integer('step_number');
            $table->string('status', 20); // PENDING, READY, IN_PROGRESS, DONE, SKIPPED
            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['batch_id']);
            $table->index(['serial_unit_id']);
            $table->index(['status']);
        });

        // One row per (serial unit, step) among live rows — partial so a
        // soft-deleted row's slot frees up (hard rule 9).
        DB::statement(
            'CREATE UNIQUE INDEX unit_steps_serial_step_unique
             ON unit_steps (serial_unit_id, step_number)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_steps');
    }
};
