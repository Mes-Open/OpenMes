<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('username', 100);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('registered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_logs');
    }
};
