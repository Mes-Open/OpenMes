<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_config_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_code', 100);
            $table->string('external_name', 255)->nullable();
            $table->string('unit_mapping', 20)->nullable();
            $table->decimal('conversion_factor', 10, 4)->default(1);
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['material_id', 'integration_config_id', 'external_code'], 'material_sources_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_sources');
    }
};
