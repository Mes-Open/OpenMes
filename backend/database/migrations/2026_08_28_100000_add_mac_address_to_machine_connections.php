<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-enrolled HTTP sensors identify their hardware by MAC address. It is a
 * label / audit identifier only (MACs are spoofable and not secret) — the
 * device token is the credential. Nullable because existing MQTT/OPC UA/Modbus
 * connections have no MAC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_connections', function (Blueprint $table) {
            $table->string('mac_address', 32)->nullable()->after('line_id');
        });
    }

    public function down(): void
    {
        Schema::table('machine_connections', function (Blueprint $table) {
            $table->dropColumn('mac_address');
        });
    }
};
