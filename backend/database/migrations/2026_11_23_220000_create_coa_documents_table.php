<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A COA is a controlled document, not a PDF generated from the latest check.
 * The document row holds the current state and the immutable version history
 * holds the snapshot that was approved / issued to a customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coa_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('coa_number', 120)->index();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->unsignedInteger('current_version')->default(1);

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_code', 80)->nullable();
            $table->string('customer_name')->nullable();
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();
            $table->string('product_code', 80)->nullable();
            $table->string('product_name')->nullable();
            $table->foreignId('product_revision_id')->nullable()->constrained('product_revisions')->nullOnDelete();
            $table->string('revision_code', 80)->nullable();
            $table->string('lot_number', 100)->nullable();

            $table->json('specification_snapshot')->nullable();
            $table->json('quality_snapshot')->nullable();
            $table->json('gate_snapshot')->nullable();

            $table->foreignId('prepared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('voided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'coa_number']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_documents');
    }
};
