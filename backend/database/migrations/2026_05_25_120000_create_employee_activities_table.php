<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();

            // Tachograph-style activity catalog
            $table->enum('type', [
                'work', 'break', 'rest', 'travel', 'setup',
                'meeting', 'training', 'maint', 'qc', 'off', 'custom',
            ])->default('work');

            // Used when type='custom' — references catalog code (e.g. 'cleaning-5s')
            $table->string('custom_code', 64)->nullable();

            // Free label override (e.g. "Lunch", "Shift handover · A → B")
            $table->string('label', 255)->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Optional linkage to a work order / step / line
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('step_name', 255)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['worker_id', 'starts_at']);
            $table->index(['tenant_id', 'starts_at']);
        });

        Schema::create('employee_activity_custom_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('label', 255);
            $table->string('color', 16)->default('#06b6d4');
            $table->string('icon', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_activity_custom_types');
        Schema::dropIfExists('employee_activities');
    }
};
