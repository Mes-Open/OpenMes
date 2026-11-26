<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A blind sensory sample belongs to one evaluator and one blend version. The
 * product/customer are intentionally absent from this table: the page has to
 * stay blind until every assigned sample is locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensory_evaluations', function (Blueprint $table) {
            $table->id();

            $table->string('blind_code', 40);
            $table->foreignId('blend_formula_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('sample_sequence')->default(0);

            // PENDING | SUBMITTED
            $table->string('status', 20)->default('PENDING');

            $table->unsignedTinyInteger('appearance_score')->nullable();
            $table->unsignedTinyInteger('color_score')->nullable();
            $table->unsignedTinyInteger('aroma_score')->nullable();
            $table->unsignedTinyInteger('taste_score')->nullable();
            $table->unsignedTinyInteger('leaf_score')->nullable();

            $table->text('comment')->nullable();
            $table->string('standard_sample_code', 80)->nullable();
            $table->json('standard_scores')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blind_code', 'tenant_id']);
            $table->index(['evaluator_id', 'status', 'sample_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensory_evaluations');
    }
};
