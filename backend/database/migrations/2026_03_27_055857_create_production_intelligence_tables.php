<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->float('cycle_time_seconds')->nullable();
            $table->float('ideal_cycle_time_seconds')->nullable();
            $table->boolean('is_micro_stop')->default(false);
            $table->jsonb('telemetry')->nullable();
            $table->timestamps();

            $table->index(['workstation_id', 'started_at']);
            $table->index(['work_order_id', 'started_at']);
        });

        Schema::create('quality_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type'); // GOOD, SCRAP, REWORK
            $table->float('quantity')->default(1.0);
            $table->foreignId('anomaly_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workstation_id', 'occurred_at']);
        });

        Schema::create('tool_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type'); // CHANGE, WEAR_CHECK, BREAKAGE, MAINTENANCE
            $table->timestamp('occurred_at');
            $table->float('usage_count_delta')->default(0);
            $table->float('wear_percentage_after')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['tool_id', 'occurred_at']);
        });

        Schema::table('downtime_events', function (Blueprint $table) {
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('loss_category')->nullable(); // For Six Big Losses
        });
    }

    public function down(): void
    {
        Schema::table('downtime_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worker_id');
            $table->dropConstrainedForeignId('tool_id');
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropColumn('loss_category');
        });
        Schema::dropIfExists('tool_events');
        Schema::dropIfExists('quality_events');
        Schema::dropIfExists('production_cycles');
    }
};
