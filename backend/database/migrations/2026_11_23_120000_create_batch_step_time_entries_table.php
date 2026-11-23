<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Independent operator and machine time per operation (#400, item 3).
 *
 * A batch step keeps its single start / completion and its confirmed elapsed,
 * setup and run minutes. Next to that, any number of time entries record who
 * worked on it (labor, per user) and how long a machine ran for it (machine,
 * per workstation), each with its own start and end, so a machine can run
 * unattended while its operator is clocked onto another step.
 *
 * At most one open entry per operator (labor) and per workstation (machine):
 * enforced by partial unique indexes, so two terminals cannot open overlapping
 * time for the same person or machine at the same instant.
 *
 * The step carries the de-duplicated totals so reports and the API read them
 * without summing entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_step_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_step_id')->constrained()->cascadeOnDelete();
            // labor | machine
            $table->string('kind', 10);
            // labor: the operator whose time this is
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // machine: the workstation that ran
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            // operator (clocked on the terminal) | machine (connectivity signal) | manual (entered afterwards)
            $table->string('source', 10);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['batch_step_id', 'kind']);
            $table->index(['user_id', 'started_at']);
            $table->index(['workstation_id', 'started_at']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX batch_step_time_entries_open_labor_unique
             ON batch_step_time_entries (user_id)
             WHERE kind = 'labor' AND ended_at IS NULL AND deleted_at IS NULL"
        );
        DB::statement(
            "CREATE UNIQUE INDEX batch_step_time_entries_open_machine_unique
             ON batch_step_time_entries (workstation_id)
             WHERE kind = 'machine' AND ended_at IS NULL AND deleted_at IS NULL"
        );

        Schema::table('batch_steps', function (Blueprint $table) {
            // Person-minutes on the step: each operator's time counted once, summed over operators.
            $table->unsignedInteger('actual_labor_minutes')->nullable()->after('actual_run_minutes');
            // Machine-minutes on the step: each workstation's running time counted once.
            $table->unsignedInteger('actual_machine_minutes')->nullable()->after('actual_labor_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropColumn(['actual_labor_minutes', 'actual_machine_minutes']);
        });
        Schema::dropIfExists('batch_step_time_entries');
    }
};
