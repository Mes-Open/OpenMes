<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Engineering CAD documents (#179) associated polymorphically with a material,
 * product type, product revision, subassembly, process template or step. Files
 * live on the private disk under server-generated paths; released revisions are
 * immutable and snapshotted onto work orders at release.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineering_documents', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner. entity_type is a short allowlisted key
            // (material | product_type | product_revision | subassembly |
            // process_template | template_step), resolved by the model — not a
            // FQCN, so it stays stable and matches the REST API.
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');

            $table->string('original_filename');
            $table->string('package_type', 32);          // EngineeringPackageType
            $table->string('document_type', 64)->nullable(); // free label e.g. assembly_model, drawing
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);      // stored/compressed bytes
            $table->unsignedBigInteger('extracted_size')->nullable(); // interactive packages (Phase 3)
            $table->string('entry_point')->nullable();                // interactive packages (Phase 3)

            $table->string('revision', 40);
            $table->string('checksum', 128);             // sha256 hex
            $table->string('storage_path');              // private, non-predictable

            $table->string('lifecycle_status', 16)->default('draft'); // EngineeringDocumentLifecycle
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['lifecycle_status']);
        });

        // One representation of a given package type per (entity, revision) among
        // live rows — a partial unique so the slot frees up after a soft delete.
        DB::statement(
            'CREATE UNIQUE INDEX engineering_documents_entity_rev_pkg_unique
             ON engineering_documents (entity_type, entity_id, revision, package_type)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_documents');
    }
};
