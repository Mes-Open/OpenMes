<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('material_type_id')->constrained()->restrictOnDelete();
            $table->string('unit_of_measure', 20)->default('pcs');
            $table->string('tracking_type', 10)->default('none'); // none, batch, serial
            $table->decimal('default_scrap_percentage', 5, 2)->default(0);
            $table->json('extra_data')->nullable();
            $table->string('external_code', 100)->nullable();
            $table->string('external_system', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
            $table->index(['external_code', 'external_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
