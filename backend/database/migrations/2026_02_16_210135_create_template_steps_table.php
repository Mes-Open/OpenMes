<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained()->onDelete('cascade');
            $table->integer('step_number');
            $table->string('name', 255);
            $table->text('instruction')->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->foreignId('workstation_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['process_template_id', 'step_number']);
            $table->index('process_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_steps');
    }
};
