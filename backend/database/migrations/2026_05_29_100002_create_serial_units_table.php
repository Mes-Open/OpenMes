<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-unit (serial) tracking — activates the `tracking_type = 'serial'` Material
 * mode. Each row is a uniquely identified physical unit whose full process
 * history is captured in `unit_step_history`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_units', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no', 100);
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('status', 20)->default('in_production'); // in_production / completed / scrapped / shipped
            $table->timestamp('produced_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->json('extra_data')->nullable();
            $table->timestamps();

            $table->unique(['serial_no', 'tenant_id']);
            $table->index(['status']);
            $table->index(['work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_units');
    }
};
