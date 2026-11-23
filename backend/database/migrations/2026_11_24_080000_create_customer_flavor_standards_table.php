<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 客户风味标准：客户对某一产品（或产品族）的放行规格。
 *
 * 与 QualityCheckTemplate 的区别：检验模板回答“怎么检”，客户标准回答
 * “按客户要求，检到什么程度算合格”。COA 签发时冻结的是后者，检验记录只是
 * 证明实际结果满足它。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_flavor_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('code', 80)->index();
            $table->string('name');
            $table->string('version', 20)->default('V1');
            $table->string('status', 20)->default('ACTIVE')->index();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // 为空表示该客户对这一产品族的所有产品通用；有值则优先匹配具体产品。
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            // 茶底类的核心放行指标：水分、感官、拼配偏差、异物、净含量。
            $table->decimal('moisture_min', 6, 3)->nullable();
            $table->decimal('moisture_max', 6, 3)->nullable();
            $table->string('sensory_aroma')->nullable();
            $table->string('sensory_taste')->nullable();
            $table->string('sensory_liquor')->nullable();
            $table->decimal('blend_tolerance', 6, 3)->nullable();
            $table->boolean('foreign_matter_required')->default(true);
            $table->decimal('net_weight_min', 10, 3)->nullable();
            $table->decimal('net_weight_max', 10, 3)->nullable();

            $table->text('packaging_requirement')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'code', 'version']);
            $table->index(['customer_id', 'product_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_flavor_standards');
    }
};
