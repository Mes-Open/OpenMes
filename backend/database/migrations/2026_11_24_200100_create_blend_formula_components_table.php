<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentages, tolerances and the two scale-up readings. A component can
 * point at a tea base dossier; component_name stays denormalized so a frozen
 * formula still reads correctly if the dossier is retired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blend_formula_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blend_formula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tea_base_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component_name', 120);

            $table->decimal('standard_ratio', 5, 2);
            $table->decimal('tolerance', 5, 2)->default(2.00);
            $table->decimal('small_actual', 5, 2)->nullable();
            $table->decimal('large_actual', 5, 2)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['blend_formula_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blend_formula_components');
    }
};
