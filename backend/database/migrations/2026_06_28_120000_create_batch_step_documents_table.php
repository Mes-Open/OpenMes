<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents attached to a production step for shop-floor document control.
 * A document can be marked mandatory and validatable; a step with an
 * unvalidated mandatory document cannot be completed (enforced in
 * BatchService::completeStep). Records who validated it and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_step_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_step_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);                 // document title / label
            $table->string('reference', 255)->nullable(); // doc number, URL, revision
            // Optional inline file (the document itself), mirroring attachments.
            $table->string('file_path', 1024)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_mandatory')->default(true);        // blocks completion when true
            $table->boolean('requires_validation')->default(true); // must be validated to pass the gate
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['batch_step_id', 'validated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_step_documents');
    }
};
