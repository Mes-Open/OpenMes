<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One immutable snapshot per COA version. Re-issuing a COA never edits the
 * prior version — it adds a new version and keeps the old one auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coa_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('coa_document_id')->constrained('coa_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('DRAFT')->index();
            $table->json('snapshot');

            $table->string('change_reason')->nullable();

            $table->foreignId('prepared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('voided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->unique(['coa_document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_document_versions');
    }
};
