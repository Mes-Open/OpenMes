<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Long-lived credential for a self-enrolled HTTP sensor, bound 1:1 to its
 * machine_connection. Deliberately has NO expiry — a fixed device runs for
 * years — so the only way to cut it off is a soft delete from the admin panel
 * (which is exactly the intended revoke path). Only the SHA-256 hash is stored;
 * the plaintext is returned once from the enrollment response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained('machine_connections')->cascadeOnDelete();
            $table->string('token_prefix', 16);
            $table->string('token_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['is_active', 'tenant_id']);
        });

        DB::statement('CREATE UNIQUE INDEX device_tokens_token_hash_unique ON device_tokens (token_hash) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
