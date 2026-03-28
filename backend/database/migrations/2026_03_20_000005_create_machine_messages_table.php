<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machine_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 500);
            $table->longText('raw_payload');
            $table->json('parsed_data')->nullable();
            $table->json('actions_triggered')->nullable();
            $table->enum('processing_status', ['ok', 'error', 'skipped'])->default('ok');
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at');

            $table->index(['machine_connection_id', 'received_at']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_messages');
    }
};
