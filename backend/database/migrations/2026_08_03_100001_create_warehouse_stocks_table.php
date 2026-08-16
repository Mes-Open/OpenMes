<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-warehouse stock balances (#212).
 *
 * One row per (warehouse, item[, lot]) where item is either a material (raw
 * material / component) or a product type (finished goods) — the two are kept
 * in separate nullable FKs rather than a polymorphic pair so the database can
 * enforce referential integrity on both.
 *
 * Balances are derived: stock documents post deltas here, and an ERP stock sync
 * can overwrite them wholesale (`erp_synced_at` records when). Rows are never
 * soft-deleted — a balance that drops to zero stays as a zero row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            // Exactly one of these two is set — enforced in the application
            // (WarehouseStock::assertItem) and by the partial uniques below.
            $table->foreignId('material_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained()->cascadeOnDelete();

            // Optional lot-level breakdown for lot-tracked materials. A row with
            // a NULL lot is the untracked ("bulk") balance for that material.
            $table->foreignId('material_lot_id')->nullable()->constrained()->cascadeOnDelete();

            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit_of_measure', 20)->nullable();

            // When this balance was last overwritten by an ERP stock sync.
            $table->timestamp('erp_synced_at')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['material_id', 'warehouse_id']);
            $table->index(['product_type_id', 'warehouse_id']);
        });

        // One balance per slot. Three partial indexes instead of one composite
        // unique because NULLs compare as distinct — without them the same
        // (warehouse, material) bulk row could be inserted twice.
        DB::statement(
            'CREATE UNIQUE INDEX warehouse_stocks_material_lot_unique
             ON warehouse_stocks (warehouse_id, material_id, material_lot_id)
             WHERE material_id IS NOT NULL AND material_lot_id IS NOT NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX warehouse_stocks_material_bulk_unique
             ON warehouse_stocks (warehouse_id, material_id)
             WHERE material_id IS NOT NULL AND material_lot_id IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX warehouse_stocks_product_type_unique
             ON warehouse_stocks (warehouse_id, product_type_id)
             WHERE product_type_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
