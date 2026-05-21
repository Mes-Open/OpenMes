<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // What to schedule (same target options as maintenance_events)
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();

            // Event template fields (used when generating events)
            $table->enum('event_type', ['planned', 'corrective', 'inspection'])->default('planned');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cost_source_id')->nullable()->constrained()->nullOnDelete();

            // Recurrence
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'annually', 'by_hours']);
            $table->unsignedInteger('interval_value')->default(1); // every N days/weeks/months/hours
            $table->time('preferred_time')->nullable();             // for daily/weekly/monthly — time of day
            $table->unsignedInteger('lead_time_days')->default(0);  // how many days before next_due_at to generate (0 = on the day)

            // State
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamp('next_due_at');
            $table->boolean('is_active')->default(true);

            // Audit
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
