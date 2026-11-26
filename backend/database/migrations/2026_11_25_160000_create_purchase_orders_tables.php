<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders and their line-level receipt link (#purchasing-loop).
 *
 * A purchase order is the commitment to a supplier. Receiving still posts
 * through the warehouse stock-document path, so warehouse balances, the stock
 * ledger and inbound inspections stay on the one existing posting path. The
 * columns added to stock_documents / stock_document_lines only record which
 * purchase order and line a receipt settles; no balance is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();
            $table->foreignId('supplier_id')->constrained('companies')->restrictOnDelete();
            // open | closed | cancelled — no draft step: saving the order is the commitment.
            $table->string('status', 20)->default('open');
            $table->date('ordered_at');
            $table->date('expected_at')->nullable();
            $table->string('currency', 3)->default('CNY');
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'expected_at']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_no')->default(1);
            $table->decimal('quantity_ordered', 15, 3);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->string('unit_of_measure', 10)->nullable();
            $table->date('expected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_no']);
            $table->index(['material_id']);
        });

        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->index(['purchase_order_id']);
        });

        Schema::table('stock_document_lines', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->unique(['stock_document_id', 'purchase_order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_document_lines', function (Blueprint $table) {
            $table->dropUnique(['stock_document_id', 'purchase_order_line_id']);
            $table->dropConstrainedForeignId('purchase_order_line_id');
        });

        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropIndex(['purchase_order_id']);
            $table->dropConstrainedForeignId('purchase_order_id');
        });

        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
