<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist items defined on a process (template) step. Reusable definition
 * resolved live at the operator workstation; completion is recorded per batch
 * step in batch_step_checklist_completions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_step_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_step_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 500);
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['process_template_id', 'template_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_step_checklist_items');
    }
};
