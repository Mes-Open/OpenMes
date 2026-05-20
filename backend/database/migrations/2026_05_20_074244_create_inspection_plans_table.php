<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Scope: per material OR per material_type (exactly one). If both null,
            // the plan is "generic" and an inspector picks it ad-hoc.
            $table->foreignId('material_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('material_type_id')->nullable()->constrained()->cascadeOnDelete();

            // [{ name, type: visual|measurement|functional|pass_fail, unit?, min?, max?, required: bool }]
            $table->json('criteria');

            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['material_id', 'is_active']);
            $table->index(['material_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_plans');
    }
};
