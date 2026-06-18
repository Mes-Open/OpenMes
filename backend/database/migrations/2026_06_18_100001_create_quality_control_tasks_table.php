<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fired instance of a quality-control trigger (#105) — "a control is due".
 * Links the requirement to the work order / batch / machine (line + workstation)
 * so results can be recorded against them. Once performed it links the recorded
 * QualityCheck and, on failure, the raised non-conformance Issue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_control_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_control_trigger_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('due'); // due | in_progress | done | skipped

            // What the control is recorded against (the AC's WO / machine link).
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();

            $table->string('due_reason', 255)->nullable(); // human snapshot, e.g. "after changeover on Line 2"
            $table->foreignId('quality_check_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('fired_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['status', 'batch_id']);
            $table->index(['quality_control_trigger_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_control_tasks');
    }
};
