<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment calibration register. A locked or overdue record is a hard gate:
 * the weighing screen checks the scale code before it accepts a measurement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_records', function (Blueprint $table) {
            $table->id();

            $table->string('equipment_code', 50);
            $table->string('equipment_name', 150);
            $table->string('equipment_kind', 50);
            $table->string('location', 100)->nullable();

            $table->timestamp('last_calibrated_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->string('certificate_no', 100)->nullable();
            $table->string('last_result', 20)->nullable();

            // ACTIVE | LOCKED. Overdue and due-soon are derived from next_due_at
            // so the page can answer "how many days left" without stale flags.
            $table->string('status', 20)->default('ACTIVE');
            $table->text('lock_reason')->nullable();
            $table->text('unlock_condition')->nullable();

            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['equipment_code', 'tenant_id']);
            $table->index(['status', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_records');
    }
};
