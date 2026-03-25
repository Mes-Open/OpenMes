<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workstation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->constrained()->cascadeOnDelete();
            $table->string('state'); // e.g., RUNNING, STOPPED, MAINTENANCE, IDLE
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->jsonb('metadata')->nullable(); // For OPC-UA/PLC tag data
            $table->timestamps();

            $table->index(['workstation_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workstation_states');
    }
};
