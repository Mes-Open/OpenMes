<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_per_unit', 12, 4);
            $table->decimal('scrap_percentage', 5, 2)->default(0);
            $table->string('consumed_at', 10)->default('start'); // start, during, end
            $table->integer('sort_order')->default(0);
            $table->json('extra_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['process_template_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
    }
};
