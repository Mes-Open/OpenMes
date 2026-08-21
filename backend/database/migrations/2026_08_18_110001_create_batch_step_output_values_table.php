<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-execution recorded value of a typed step output: the operator filled the
 * output on a specific batch step. Typed columns hold the scalar value; a
 * `picture` output stores the file on the private disk (path/mime/size), mirror
 * of batch_step_documents. Soft-deletable (audited) so a re-record keeps history;
 * uniqueness is partial (live rows only) so a value can be re-recorded after
 * clearing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_step_output_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_id')->constrained('template_step_outputs')->cascadeOnDelete();

            // Typed scalar value (exactly one is set per the output's value_type).
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();

            // Picture value.
            $table->string('file_path', 1024)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // One live value per (step, output); a soft-deleted row doesn't block a
        // re-record. Partial unique works on both Postgres and SQLite.
        DB::statement('CREATE UNIQUE INDEX batch_step_output_values_unique ON batch_step_output_values (batch_step_id, output_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_step_output_values');
    }
};
