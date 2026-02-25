<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_product_type', function (Blueprint $table) {
            $table->foreignId('line_id')->constrained('lines')->cascadeOnDelete();
            $table->foreignId('product_type_id')->constrained('product_types')->cascadeOnDelete();
            $table->primary(['line_id', 'product_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_product_type');
    }
};
