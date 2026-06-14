<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crew-level break windows — recurring intra-day pauses (lunch, tea break)
 * that apply to a whole crew on given weekdays. Unlike worker_absences
 * (day-level, per worker) these are time-of-day, per crew, and recurring; they
 * feed WorkerAvailabilityService so a worker is "on break" when their crew is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_break_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->time('start_time');
            $table->time('end_time');
            // ISO weekdays the window applies on: 1 = Mon … 7 = Sun.
            $table->json('days_of_week');
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['crew_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_break_windows');
    }
};
