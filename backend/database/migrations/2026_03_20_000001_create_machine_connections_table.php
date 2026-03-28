<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machine_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->enum('protocol', ['mqtt', 'opcua', 'modbus', 'rest'])->default('mqtt');
            $table->boolean('is_active')->default(false);
            $table->enum('status', ['disconnected', 'connected', 'connecting', 'error'])->default('disconnected');
            $table->string('status_message', 500)->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->unsignedBigInteger('messages_received')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_connections');
    }
};
