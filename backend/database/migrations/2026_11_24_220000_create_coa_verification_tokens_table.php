<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coa_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coa_document_id')->constrained('coa_documents')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['coa_document_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_verification_tokens');
    }
};
