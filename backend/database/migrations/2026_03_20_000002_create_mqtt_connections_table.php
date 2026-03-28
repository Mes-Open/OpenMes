<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mqtt_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained()->cascadeOnDelete();
            $table->string('broker_host', 255);
            $table->unsignedSmallInteger('broker_port')->default(1883);
            $table->string('client_id', 100)->nullable();
            $table->string('username', 100)->nullable();
            $table->text('password_encrypted')->nullable();
            $table->boolean('use_tls')->default(false);
            $table->text('ca_cert')->nullable();
            $table->unsignedSmallInteger('keep_alive_seconds')->default(60);
            $table->unsignedTinyInteger('qos_default')->default(0);
            $table->boolean('clean_session')->default(true);
            $table->unsignedSmallInteger('connect_timeout')->default(10);
            $table->unsignedSmallInteger('reconnect_delay_seconds')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mqtt_connections');
    }
};
