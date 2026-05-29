<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-unit "birth certificate" entries — one row each time a serialised unit is
 * processed at a workstation. Captures the operator, the step, a parameter
 * snapshot (sensor/measurement values) and a high-precision timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_step_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serial_unit_id')->constrained('serial_units')->cascadeOnDelete();
            $table->foreignId('batch_step_id')->nullable()->constrained('batch_steps')->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('parameters')->nullable();   // sensor / measurement snapshot at processing time
            $table->string('result', 20)->nullable(); // pass / fail / rework
            $table->string('notes', 500)->nullable();
            $table->timestamp('processed_at', 6);      // microsecond precision for ordering within a station
            $table->timestamps();

            $table->index(['serial_unit_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_step_history');
    }
};
