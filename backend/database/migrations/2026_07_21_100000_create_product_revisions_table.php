<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product revisions (#180) — a formal, versioned released configuration of a
 * product type. A revision groups the exact process template (which carries the
 * BOM + steps) that applies to a manufactured revision, plus lifecycle metadata.
 *
 * The (product_type_id, revision_code) uniqueness is a PARTIAL index scoped to
 * live rows, so a code can be re-used after a revision is soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
            $table->string('revision_code', 50);
            $table->string('description')->nullable();
            $table->string('lifecycle_status', 20)->default('draft');

            // The released configuration this revision points at. Nullable while
            // DRAFT; required to be set before RELEASED (enforced in the request).
            $table->foreignId('process_template_id')->nullable()->constrained()->nullOnDelete();

            $table->string('change_reason')->nullable();
            $table->string('external_ref')->nullable();

            // Optional effectivity window (informational for now).
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();

            $table->timestamp('released_at')->nullable();
            $table->timestamp('obsolete_at')->nullable();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['product_type_id', 'lifecycle_status']);
        });

        // Partial unique: a revision code is unique per product type among live
        // (non-deleted) rows only, so it can be re-used after a soft delete.
        DB::statement(
            'CREATE UNIQUE INDEX product_revisions_type_code_unique '
            .'ON product_revisions (product_type_id, revision_code) '
            .'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_revisions');
    }
};
