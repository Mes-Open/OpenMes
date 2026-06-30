<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-execution completion of a step checklist item: the operator ticked the
 * item on a specific batch step. Soft-deletable (audited) so an un-check keeps
 * the who/checked-when evidence; the uniqueness is partial (live rows only) so
 * the same item can be re-checked after an un-check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_step_checklist_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('template_step_checklist_items')->cascadeOnDelete();
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // One live completion per (step, item); a soft-deleted row doesn't block a
        // re-check. Partial unique indexes work on both Postgres and SQLite.
        DB::statement('CREATE UNIQUE INDEX batch_step_checklist_completions_unique ON batch_step_checklist_completions (batch_step_id, checklist_item_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_step_checklist_completions');
    }
};
