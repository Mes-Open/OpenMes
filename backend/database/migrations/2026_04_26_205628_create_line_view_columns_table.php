<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_view_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('source', 50)->default('extra_data');
            $table->string('key', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['line_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_view_columns');
    }
};
