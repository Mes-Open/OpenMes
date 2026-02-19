<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('anomaly_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('product_name', 255);
            $table->decimal('planned_qty', 10, 2);
            $table->decimal('actual_qty', 10, 2);
            $table->decimal('deviation_pct', 8, 2)->nullable(); // computed in model accessor
            $table->enum('status', ['draft', 'processed'])->default('draft');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['work_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_anomalies');
    }
};
