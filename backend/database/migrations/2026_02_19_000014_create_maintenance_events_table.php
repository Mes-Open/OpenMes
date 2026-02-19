<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->enum('event_type', ['planned', 'corrective', 'inspection'])->default('planned');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->string('currency', 10)->default('PLN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_events');
    }
};
