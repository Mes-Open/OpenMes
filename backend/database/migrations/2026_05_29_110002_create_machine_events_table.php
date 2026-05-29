<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only event store for machine signals. Every state transition,
 * counter pulse and alarm is recorded with a correlation id and a
 * high-precision timestamp, enabling replay and edge→cloud sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->foreignId('machine_connection_id')->nullable()->constrained('machine_connections')->nullOnDelete();
            $table->string('event_type', 40); // state_change / counter / alarm / telemetry
            $table->string('state_from', 20)->nullable();
            $table->string('state_to', 20)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('event_timestamp', 6);
            $table->uuid('correlation_id')->nullable();
            $table->boolean('synced_to_cloud')->default(false);
            $table->timestamps();

            $table->index(['workstation_id', 'event_timestamp']);
            $table->index(['event_type']);
            $table->index(['synced_to_cloud']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_events');
    }
};
