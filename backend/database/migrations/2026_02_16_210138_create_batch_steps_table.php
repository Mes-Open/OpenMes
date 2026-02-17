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
        Schema::create('batch_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->integer('step_number');
            $table->string('name', 255);
            $table->text('instruction')->nullable();
            $table->string('status', 20); // PENDING, IN_PROGRESS, DONE, SKIPPED
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('started_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'step_number']);
            $table->index('batch_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_steps');
    }
};
