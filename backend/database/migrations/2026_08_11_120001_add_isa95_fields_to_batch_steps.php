<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISA-95 additions to batch steps (#52). All nullable — existing orders unchanged.
 * `workstation_type_id` + `estimated_duration_minutes` are carried down from the
 * work-order snapshot (planning context / required Equipment Class shown until a
 * specific machine is assigned). The `actual_*` columns are the ISA-95 Level-3,
 * operator-confirmed times, stored SEPARATELY from the existing `duration_minutes`
 * (the system wall-clock diff, kept as the recorded value for audit).
 * `assigned_*` records the supervisor pool-dispatch assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->foreignId('workstation_type_id')
                ->nullable()
                ->after('workstation_id')
                ->constrained('workstation_types')
                ->nullOnDelete();
            $table->unsignedInteger('estimated_duration_minutes')->nullable()->after('workstation_type_id');
            // Standard (L4) times carried for reference so the operator step object is
            // self-sufficient (drives the opt-in actual-times modal + its prefills).
            $table->unsignedInteger('setup_time_minutes')->nullable()->after('estimated_duration_minutes');
            $table->decimal('run_time_per_unit_minutes', 8, 2)->nullable()->after('setup_time_minutes');
            $table->unsignedInteger('actual_elapsed_minutes')->nullable()->after('duration_minutes');
            $table->unsignedInteger('actual_setup_minutes')->nullable()->after('actual_elapsed_minutes');
            $table->unsignedInteger('actual_run_minutes')->nullable()->after('actual_setup_minutes');
            $table->foreignId('assigned_by_id')->nullable()->after('actual_run_minutes')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workstation_type_id');
            $table->dropConstrainedForeignId('assigned_by_id');
            $table->dropColumn([
                'estimated_duration_minutes', 'setup_time_minutes', 'run_time_per_unit_minutes',
                'actual_elapsed_minutes', 'actual_setup_minutes', 'actual_run_minutes', 'assigned_at',
            ]);
        });
    }
};
