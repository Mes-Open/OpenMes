<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_weighing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number')->nullable();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material_code', 100)->nullable();
            $table->string('material_name');
            $table->string('unit_of_measure', 20)->default('kg');
            $table->decimal('target_qty', 14, 4);
            $table->decimal('actual_qty', 14, 4);
            $table->decimal('tolerance_pct', 6, 3)->default(0.500);
            $table->decimal('variance_qty', 14, 4)->default(0);
            $table->decimal('variance_pct', 8, 3)->default(0);
            $table->string('status', 20)->default('PASS');
            $table->string('scale_code', 100)->nullable();
            $table->foreignId('weighed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('weighed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'material_id', 'step_number'], 'bwr_batch_material_step_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_weighing_records');
    }
};
