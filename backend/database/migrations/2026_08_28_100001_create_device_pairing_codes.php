<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time enrollment codes for self-registering HTTP sensors. An admin
 * generates a code in the panel (optionally pre-assigning the line/workstation
 * the device will feed); the device redeems it once at first contact and is
 * issued a long-lived device token. Only the SHA-256 hash of the code is
 * stored — the plaintext is shown once at generation and never again. Mirrors
 * the api_keys credential pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Label for the device this code will create');
            // First few chars of the plaintext code, shown in the admin UI.
            $table->string('code_prefix', 16);
            // SHA-256 hex digest of the full plaintext code (64 chars).
            $table->string('code_hash', 64);
            // Target the enrolled device will feed (both optional; admin may bind later).
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            // The connection created when this code was redeemed (audit trail).
            $table->foreignId('used_by_connection_id')->nullable()
                ->constrained('machine_connections')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['is_active', 'tenant_id']);
        });

        // Hash is unique among live (non-deleted) codes only, so a revoked code's
        // hash slot is freed on soft delete. Works on PostgreSQL (prod) + SQLite (tests).
        DB::statement('CREATE UNIQUE INDEX device_pairing_codes_code_hash_unique ON device_pairing_codes (code_hash) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairing_codes');
    }
};
