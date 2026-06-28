<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-execution completion of a step checklist item: the operator ticked the
 * item on a specific batch step. Presence of a row means "checked"; un-checking
 * removes it. Records who checked it and when.
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

            $table->unique(['batch_step_id', 'checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_step_checklist_completions');
    }
};
