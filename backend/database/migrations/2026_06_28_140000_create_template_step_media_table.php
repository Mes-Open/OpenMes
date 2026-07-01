<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich work-instruction media attached to a process (template) step: images,
 * PDFs and videos. Defined once on the template step and resolved live at the
 * operator workstation (like reference photos), so updates reach in-flight
 * work orders without re-snapshotting. Multiple media per step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_step_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_step_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('media_type', 10);          // image | pdf | video
            $table->string('title', 255)->nullable();
            $table->string('storage_path', 255);       // private disk, server-generated name
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['process_template_id', 'template_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_step_media');
    }
};
