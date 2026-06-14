<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Worker absences — first-class "this worker is unavailable for a date range"
 * (vacation / sick / personal / training / other). Distinct from
 * employee_activities (an intra-shift tachograph): absences are day-level and
 * drive availability + capacity. Supervisor-recorded, so status defaults to
 * approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['vacation', 'sick', 'personal', 'training', 'other'])->default('other');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('all_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('status', ['approved', 'pending', 'rejected'])->default('approved');
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['worker_id', 'starts_on', 'ends_on']);
            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_absences');
    }
};
