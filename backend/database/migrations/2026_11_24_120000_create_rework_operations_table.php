<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Executable rework routing (#400, item 2).
 *
 * A rework operation sends pieces of a non-conformance (an issue) found at a
 * step back to an earlier operation. While it is open, those pieces are held
 * at the step where the defect was found (`batch_steps.rework_held_qty`): they
 * cannot move on, so they are never counted as produced twice, and the step
 * cannot be completed until the rework is accepted, scrapped or cancelled.
 *
 * The earlier step's own record (quantities, times, material) is never
 * changed; the rework carries its own history and its own operator and machine
 * time (time entries tagged with `rework_operation_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rework_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            // Where the defect was found and the pieces are held.
            $table->foreignId('source_batch_step_id')->constrained('batch_steps')->cascadeOnDelete();
            // The earlier (or the same) operation the pieces go back to.
            $table->foreignId('target_batch_step_id')->constrained('batch_steps')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->text('reason');
            // open | in_progress | completed | cancelled
            $table->string('status', 20)->default('open');
            $table->decimal('accepted_qty', 12, 2)->nullable();
            $table->decimal('scrapped_qty', 12, 2)->nullable();
            $table->foreignId('scrap_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('actual_labor_minutes')->nullable();
            $table->unsignedInteger('actual_machine_minutes')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['batch_id', 'status']);
            $table->index(['source_batch_step_id', 'status']);
            $table->index(['status']);
        });

        Schema::table('batch_steps', function (Blueprint $table) {
            // Pieces held at this step by open rework operations (see above).
            $table->decimal('rework_held_qty', 12, 2)->default(0)->after('scrap_qty');
        });

        Schema::table('batch_step_time_entries', function (Blueprint $table) {
            // Time spent on a rework: recorded on the step it reworks, counted
            // on the rework, never on that step's own totals.
            $table->foreignId('rework_operation_id')->nullable()->after('batch_step_id')->constrained()->nullOnDelete();
        });

        // SQLite rebuilds the table for the new foreign key and recreates its
        // indexes without their WHERE clause, which would turn "one open entry
        // per operator / workstation" into "one entry ever". Recreate them
        // (a no-op change on PostgreSQL).
        $this->recreateOpenEntryIndexes();
    }

    private function recreateOpenEntryIndexes(): void
    {
        DB::statement('DROP INDEX IF EXISTS batch_step_time_entries_open_labor_unique');
        DB::statement('DROP INDEX IF EXISTS batch_step_time_entries_open_machine_unique');
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
    }

    public function down(): void
    {
        Schema::table('batch_step_time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rework_operation_id');
        });
        $this->recreateOpenEntryIndexes();
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropColumn('rework_held_qty');
        });
        Schema::dropIfExists('rework_operations');
    }
};
