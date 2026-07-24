<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API keys for machine-to-machine ERP integrations (SAP, Comarch, enova365,
 * Microsoft Dynamics / Business Central). Unlike Sanctum personal-access
 * tokens these are not bound to a user: they are long-lived service
 * credentials, carry an explicit `scopes` allowlist, an optional IP allowlist,
 * and an optional expiry. Only the SHA-256 hash of the secret is stored; the
 * plaintext key is shown once at creation and never again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            // Optional link to the ERP integration this key belongs to. When the
            // integration is deleted the key survives but is orphaned (null).
            $table->foreignId('integration_config_id')->nullable()
                ->constrained('integration_configs')->nullOnDelete();
            $table->string('name', 120)->comment('Human label, e.g. "SAP S/4HANA prod"');
            // First few chars of the plaintext key, shown in the admin UI so a
            // key can be identified without exposing the secret.
            $table->string('key_prefix', 16);
            // SHA-256 hex digest of the full plaintext key (64 chars).
            $table->string('key_hash', 64);
            $table->json('scopes')->comment('Allowed ApiScope values, e.g. ["erp:orders:import"]');
            // Optional CIDR/IP allowlist. Empty/null = allow any source IP.
            $table->json('ip_allowlist')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['is_active', 'tenant_id']);
        });

        // The hash is unique among live (non-deleted) keys only, so a rotated /
        // revoked key's hash slot is freed on soft delete. Partial unique
        // indexes work on PostgreSQL (prod) and SQLite (tests).
        DB::statement('CREATE UNIQUE INDEX api_keys_key_hash_unique ON api_keys (key_hash) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
