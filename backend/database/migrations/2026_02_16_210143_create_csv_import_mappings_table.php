<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('csv_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('mapping_config'); // JSONB - stores column mapping configuration
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_import_mappings');
    }
};
