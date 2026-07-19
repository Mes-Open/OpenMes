<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workstation client devices (shop-floor PCs running the "OpenMES Workstation"
 * client). A device self-registers against the MAIN app by IP: on first launch
 * the client generates a stable device_uuid, POSTs it to /api/workstations/register,
 * then sends a periodic /api/workstations/heartbeat. The MAIN app lists them
 * live (Admin -> Workstation devices) and derives online/offline from
 * last_seen_at — no network/infrastructure setup required beyond the operator
 * typing the MAIN IP once during install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workstation_devices', function (Blueprint $table) {
            $table->id();
            // Client-generated stable identity (persists across restarts).
            $table->string('device_uuid', 64);
            $table->string('name', 150);
            // Last known network address of the client (IPv4/IPv6).
            $table->string('ip_address', 45)->nullable();
            $table->string('hostname', 255)->nullable();
            // Client build version, for support/visibility.
            $table->string('app_version', 30)->nullable();
            // Optional line the station is assigned to (chosen on the client).
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            // Heartbeat clock — online is derived from this, not stored.
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('registered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index('last_seen_at');
        });

        // device_uuid is unique among live (non-deleted) rows so a forgotten
        // (soft-deleted) station can re-register later with the same id.
        // Partial index works on PostgreSQL (prod) and SQLite (tests).
        DB::statement('CREATE UNIQUE INDEX workstation_devices_device_uuid_unique ON workstation_devices (device_uuid) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('workstation_devices');
    }
};
