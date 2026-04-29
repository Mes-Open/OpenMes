<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_configs', function (Blueprint $table) {
            $table->id();
            $table->string('system_type', 50); // subiekt_gt, subiekt_nexo, wms, erp_custom
            $table->string('system_name', 100);
            $table->json('api_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['system_type', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_configs');
    }
};
