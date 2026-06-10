<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            // Entity-type alias (snake_case model basename, e.g. 'work_order').
            $table->string('entity_type', 64);
            // Machine key used as the JSON key inside the entity's custom_fields.
            $table->string('key', 64);
            $table->string('label', 255);
            // text|textarea|number|integer|boolean|date|datetime|select|multiselect
            $table->string('type', 32);
            // Type-specific settings: { options:[{value,label}], min, max, default, … }
            $table->json('config')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            // Null tenant_id = global definition visible to every tenant.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A key is unique per entity-type within a tenant (global keys live
            // under the null-tenant slice).
            $table->unique(['tenant_id', 'entity_type', 'key']);
            $table->index(['entity_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
