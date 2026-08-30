<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typed operator-output definitions on a process (template) step: what the
 * operator must record at execution — a key, a label and a value type
 * (text | number | boolean | select | date | picture). Reusable definition
 * resolved live at the operator workstation; the recorded value lives per batch
 * step in batch_step_output_values. Mirrors template_step_checklist_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_step_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_step_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 100);              // e.g. output_qcpic
            $table->string('label', 255);
            $table->string('value_type', 20);        // text|number|boolean|select|date|picture
            $table->string('unit', 30)->nullable();  // for number
            $table->json('options')->nullable();     // for select
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
        Schema::dropIfExists('template_step_outputs');
    }
};
