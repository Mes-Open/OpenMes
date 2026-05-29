<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocol-agnostic tag → signal mapping. A tag points at a protocol address
 * (Modbus register, OPC UA node id, MQTT JSONPath) and declares the semantic
 * signal it carries plus an optional transform (scale/offset/value-map). This
 * is the single normalization point every protocol adapter feeds through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained('machine_connections')->cascadeOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->string('name', 100);
            $table->string('address', 255);          // 40001 / ns=2;s=State / $.sensor.value
            $table->string('signal_type', 30);       // state / good_count / reject_count / cycle_complete / telemetry / alarm
            $table->string('data_type', 20)->default('int16'); // int16/uint16/int32/uint32/float32/bool/string
            $table->string('register_type', 20)->nullable();   // holding/input/coil/discrete (Modbus)
            $table->json('transform')->nullable();   // {scale, offset, value_map:{1:RUNNING,...}}
            $table->string('unit', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['machine_connection_id', 'is_active']);
            $table->index(['workstation_id', 'signal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_tags');
    }
};
