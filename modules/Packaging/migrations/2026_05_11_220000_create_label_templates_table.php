<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32); // work_order | finished_goods | workstation_step
            $table->string('size', 32)->default('100x50'); // 100x50 | 80x40 | 62x29
            $table->jsonb('fields_config'); // {wo_number: bool, product: bool, quantity: bool, barcode: bool, qr: bool, logo: bool, lot: bool, prod_date: bool}
            $table->string('barcode_format', 16)->default('code128'); // code128 | code39 | ean13
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type'], 'idx_label_templates_tenant_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
