<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->float('cycle_time_secs'); // Time taken for a single unit completion
            $table->float('ideal_time_secs');
            $table->float('variability_pct'); // (Actual - Ideal) / Ideal
            $table->timestamp('completed_at');
            $table->jsonb('context')->nullable(); // Operator, Shift, MaterialBatch
            $table->timestamps();

            $table->index(['workstation_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_time_logs');
    }
};
