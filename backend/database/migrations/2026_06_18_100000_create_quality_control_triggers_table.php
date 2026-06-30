<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable quality-control triggers (#105). A trigger fires the referenced
 * in-process control (a QualityCheckTemplate) when a production event matches:
 * batch entering production, every N units, every N minutes, after a downtime,
 * after a setup/changeover, or roaming (manual ad-hoc). The optional scope
 * columns (all null = applies anywhere) narrow where it applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_control_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // in_production | every_n_units | every_n_minutes | after_downtime | after_setup | roaming
            $table->string('trigger_type', 30);
            // The control to run when the trigger fires.
            $table->foreignId('quality_check_template_id')->nullable()->constrained()->nullOnDelete();

            // Scope — all nullable; null means "any".
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('threshold_n')->nullable();          // N units or N minutes
            $table->unsignedInteger('downtime_min_minutes')->nullable(); // only fire after downtime >= X minutes
            $table->boolean('is_blocking')->default(false);              // hard-gate vs soft prompt
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['trigger_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_control_triggers');
    }
};
