<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('topic_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_topic_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255)->nullable();
            $table->string('field_path', 255)->nullable();
            $table->enum('action_type', [
                'update_batch_step',
                'update_work_order_qty',
                'create_issue',
                'update_line_status',
                'set_work_order_status',
                'log_event',
                'webhook_forward',
            ]);
            $table->json('action_params')->nullable();
            $table->string('condition_expr', 255)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['machine_topic_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_mappings');
    }
};
