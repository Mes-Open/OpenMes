<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISA-95 additions to process-template steps (#52). All nullable — existing
 * templates are unchanged. `workstation_type_id` lets a step require an Equipment
 * Class (a specific machine is assigned at dispatch); `setup_time_minutes` and
 * `run_time_per_unit_minutes` are the ISA-95 Level-4 standard times (planned total
 * = setup + run × qty) that flow down from an ERP BOM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->foreignId('workstation_type_id')
                ->nullable()
                ->after('workstation_id')
                ->constrained('workstation_types')
                ->nullOnDelete();
            $table->unsignedInteger('setup_time_minutes')->nullable()->after('estimated_duration_minutes');
            $table->decimal('run_time_per_unit_minutes', 8, 2)->nullable()->after('setup_time_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workstation_type_id');
            $table->dropColumn(['setup_time_minutes', 'run_time_per_unit_minutes']);
        });
    }
};
