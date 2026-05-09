<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('allocated_qty', 12, 4);
            $table->decimal('returned_qty', 12, 4)->default(0);
            $table->string('status', 20)->default('allocated'); // allocated, consumed, returned
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'material_id']);
            $table->index(['material_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_allocations');
    }
};
