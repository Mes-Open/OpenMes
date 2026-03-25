<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->string('state')->default('IDLE'); // RUNNING, IDLE, FAULT, SETUP, MAINTENANCE
            $table->float('ideal_cycle_time_secs')->nullable(); // For Performance calculation
            $table->float('min_cycle_time_secs')->nullable();
            $table->jsonb('config')->nullable(); // For communication parameters (MQTT, OPC-UA)
            $table->timestamp('last_heartbeat_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn(['state', 'ideal_cycle_time_secs', 'min_cycle_time_secs', 'config', 'last_heartbeat_at']);
        });
    }
};
