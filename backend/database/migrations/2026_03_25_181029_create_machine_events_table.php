<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->constrained();
            $table->string('event_type'); // STATE_CHANGE, TELEMETRY_HEARTBEAT, ALARM, FAULT
            $table->string('state_from')->nullable();
            $table->string('state_to')->nullable();
            $table->jsonb('payload'); // Full sensor data/telemetry at event time
            $table->timestamp('event_timestamp', 6); // Microsecond precision
            $table->boolean('synced_to_cloud')->default(false);
            $table->uuid('correlation_id')->nullable(); // For distributed event tracing
            $table->timestamps();

            $table->index(['workstation_id', 'event_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_events');
    }
};
