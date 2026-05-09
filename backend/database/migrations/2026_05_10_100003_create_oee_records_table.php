<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oee_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('record_date');
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('operating_minutes')->default(0);
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->decimal('ideal_cycle_minutes', 8, 4)->nullable();
            $table->decimal('total_produced', 12, 2)->default(0);
            $table->decimal('good_produced', 12, 2)->default(0);
            $table->decimal('scrap_qty', 12, 2)->default(0);
            $table->decimal('availability_pct', 5, 2)->nullable();
            $table->decimal('performance_pct', 5, 2)->nullable();
            $table->decimal('quality_pct', 5, 2)->nullable();
            $table->decimal('oee_pct', 5, 2)->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['line_id', 'workstation_id', 'shift_id', 'record_date'], 'oee_records_unique');
            $table->index(['record_date', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oee_records');
    }
};
