<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->foreignId('product_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prefix', 20);
            $table->string('suffix', 20)->nullable();
            $table->bigInteger('next_number')->default(1);
            $table->integer('pad_size')->default(4);
            $table->boolean('year_prefix')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_type_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_sequences');
    }
};
