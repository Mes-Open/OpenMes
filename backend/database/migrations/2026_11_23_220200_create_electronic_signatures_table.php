<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An electronic signature is bound to a record and a meaning, not just to a
 * user id. The hash is calculated over the signed payload so a later edit of
 * the same record is detectable by comparing the stored hashes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electronic_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('signable_type', 150);
            $table->unsignedBigInteger('signable_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('meaning', 60);
            $table->timestamp('signed_at');
            $table->string('record_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['signable_type', 'signable_id']);
            $table->index(['user_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_signatures');
    }
};
