<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('view_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('columns');
            $table->timestamps();
        });

        // Add view_template_id to lines
        Schema::table('lines', function (Blueprint $table) {
            $table->foreignId('view_template_id')->nullable()->constrained('view_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('view_template_id');
        });
        Schema::dropIfExists('view_templates');
    }
};
