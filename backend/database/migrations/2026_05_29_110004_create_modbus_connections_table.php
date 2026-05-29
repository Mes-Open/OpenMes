<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modbus TCP transport config, one row per machine_connection of protocol
 * 'modbus'. The poller daemon reads registers per the connection's machine_tags
 * every poll_interval_ms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modbus_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained('machine_connections')->cascadeOnDelete();
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(502);
            $table->unsignedSmallInteger('unit_id')->default(1); // slave id
            $table->unsignedInteger('poll_interval_ms')->default(1000);
            $table->unsignedSmallInteger('timeout_seconds')->default(3);
            $table->string('byte_order', 10)->default('big');   // big / little (endianness)
            $table->string('word_order', 10)->default('big');   // big / little (for 32-bit across two registers)
            $table->unsignedSmallInteger('max_registers_per_read')->default(120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modbus_connections');
    }
};
