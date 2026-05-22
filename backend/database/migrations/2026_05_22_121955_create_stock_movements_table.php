<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();

            // allocation | consume | return | receipt | adjustment | scrap | transfer
            $table->string('movement_type', 20);

            // Signed delta. Negative = stock leaves the warehouse.
            $table->decimal('quantity', 12, 4);

            // Snapshot of material.stock_quantity AFTER this movement was applied.
            // Used for audit / ledger reconciliation without recomputing from scratch.
            $table->decimal('balance_after', 12, 4);

            // Polymorphic-ish: which entity caused the movement.
            // 'batch' | 'batch_step' | 'inspection' | 'manual_adjust' | 'receipt'
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->text('reason')->nullable();

            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['material_id', 'performed_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['movement_type', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
