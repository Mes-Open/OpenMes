<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lines of a stock document (#212). Quantities are always positive — the parent
 * document's type decides whether they add to or subtract from the warehouse
 * balance, so the same line shape serves an issue and a receipt.
 *
 * `lot_number` is a free-text fallback for lots that exist in the ERP but have
 * no MaterialLot row yet; when material_lot_id is set it wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_document_id')->constrained()->cascadeOnDelete();

            // Exactly one of these is set, matching the parent document's type
            // (material_* → material, product_* → product type).
            $table->foreignId('material_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained()->restrictOnDelete();

            $table->foreignId('material_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lot_number', 100)->nullable();

            $table->decimal('quantity', 14, 3);
            $table->string('unit_of_measure', 20)->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            // Soft-deleted with their parent document (StockDocument::softDeleteCascades)
            // — the DB cascade above only fires on a force delete.
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['material_id']);
            $table->index(['product_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_document_lines');
    }
};
