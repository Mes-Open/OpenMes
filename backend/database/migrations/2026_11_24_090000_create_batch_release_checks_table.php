<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次放行前的现场确认记录：留样、清线、标签平衡。
 *
 * 这些不是“质量检查”的检验数据，而是批记录完整性证据；单独成表，按事件追加，
 * 放行门取每个类型的最新一条，保留历史。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_release_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('check_type', 40);
            $table->string('status', 20);
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'check_type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_release_checks');
    }
};
