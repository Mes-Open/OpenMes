<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A blend formula is a versioned recipe, not an editable draft record. The
 * version identity, standard sample and approval trail travel together so a
 * frozen version cannot silently drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blend_formulas', function (Blueprint $table) {
            $table->id();

            $table->string('code', 80);
            $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('version', 20);
            // DRAFT | SAMPLE | LARGE_SAMPLE | APPROVED | FROZEN
            $table->string('status', 20)->default('DRAFT');

            $table->string('standard_sample_code', 80)->nullable();
            $table->timestamp('standard_sample_approved_at')->nullable();
            $table->text('standard_sample_summary')->nullable();
            $table->decimal('standard_sample_blind_result', 5, 2)->nullable();

            $table->string('small_sample_result', 20)->nullable();
            $table->string('large_sample_result', 20)->nullable();

            $table->date('effective_date')->nullable();

            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('frozen_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at')->nullable();
            $table->text('freeze_reason')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
            $table->unique(['product_type_id', 'customer_id', 'version']);
            $table->index(['status', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blend_formulas');
    }
};
