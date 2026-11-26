<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次外检报告：农残、重金属、微生物等企业自检做不了的项目，由有资质的
 * 第三方实验室出具。COA 引用报告编号和结论，不把外检数据伪装成本厂检验。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_lab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();

            $table->string('lab_name');
            $table->string('report_number', 100)->index();
            $table->string('sample_number', 100)->nullable();
            $table->date('sampled_at')->nullable();
            $table->date('issued_at')->nullable();
            $table->string('overall_conclusion', 20)->default('PASS')->index();

            // [{name, category, result, unit, limit, conclusion, note}]
            $table->json('items');
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'report_number']);
            $table->index(['batch_id', 'overall_conclusion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_lab_tests');
    }
};
