<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPC UA transport config, one row per machine_connection of protocol 'opcua'.
 * OpenMES does not speak OPC UA natively (binary protocol, security policies,
 * X.509 secure channel); an external gateway sidecar subscribes and republishes
 * normalized signals into the MQTT pipeline. This row generates the sidecar's
 * config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcua_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained('machine_connections')->cascadeOnDelete();
            $table->string('endpoint_url', 500);                    // opc.tcp://host:4840
            $table->string('security_policy', 50)->default('None'); // None / Basic256Sha256
            $table->string('security_mode', 30)->default('None');   // None / Sign / SignAndEncrypt
            $table->string('auth_mode', 30)->default('anonymous');  // anonymous / username / certificate
            $table->string('username', 100)->nullable();
            $table->text('password_encrypted')->nullable();
            $table->text('client_cert')->nullable();
            $table->text('client_key_encrypted')->nullable();
            $table->unsignedInteger('publishing_interval_ms')->default(1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcua_connections');
    }
};
