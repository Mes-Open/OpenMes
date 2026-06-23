<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit crew ↔ line assignment: which production lines each crew is
 * responsible for staffing. Consumed by the schedule-capacity crew axis to
 * attribute labor demand (falls back to worker-derived lines when a crew has
 * no explicit assignment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['crew_id', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_line');
    }
};
