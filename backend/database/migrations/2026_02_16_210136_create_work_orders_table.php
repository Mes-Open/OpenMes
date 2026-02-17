<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 100)->unique();
            $table->foreignId('line_id')->constrained()->onDelete('restrict');
            $table->foreignId('product_type_id')->constrained()->onDelete('restrict');
            $table->json('process_snapshot'); // JSONB - versioned copy of process_template
            $table->decimal('planned_qty', 10, 2);
            $table->decimal('produced_qty', 10, 2)->default(0);
            $table->string('status', 20); // PENDING, IN_PROGRESS, BLOCKED, DONE, CANCELLED
            $table->integer('priority')->default(0);
            $table->timestamp('due_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['line_id', 'status']);
            $table->index('order_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
