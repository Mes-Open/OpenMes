<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-sliced machine state history per workstation. One open row (ended_at
 * null) is the current state; transitions close the previous row and open a
 * new one. Drives availability and auto-downtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workstation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->constrained('workstations')->cascadeOnDelete();
            $table->string('state', 20); // RUNNING / IDLE / STOPPED / FAULT / SETUP
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->string('source', 20)->default('machine'); // machine / manual
            $table->json('metadata')->nullable(); // telemetry snapshot at transition
            $table->timestamps();

            $table->index(['workstation_id', 'started_at']);
            $table->index(['workstation_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workstation_states');
    }
};
