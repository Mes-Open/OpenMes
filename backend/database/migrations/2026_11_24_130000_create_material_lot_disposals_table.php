<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lot_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('material_lot_id')->constrained('material_lots')->cascadeOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained('inspections')->nullOnDelete();
            $table->string('disposition', 40);
            $table->decimal('quantity', 14, 4);
            $table->foreignId('stock_document_id')->nullable()->constrained('stock_documents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('disposed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disposed_at');
            $table->timestamps();

            $table->index(['material_lot_id', 'disposed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lot_disposals');
    }
};
