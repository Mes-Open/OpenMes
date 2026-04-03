<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machine_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained()->cascadeOnDelete();
            $table->string('topic_pattern', 500);
            $table->enum('payload_format', ['json', 'plain', 'csv', 'hex'])->default('json');
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('machine_connection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_topics');
    }
};
