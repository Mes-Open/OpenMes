<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock documents (#212) — the warehouse paperwork production generates:
 * material issued to a work order (raw-material release) and finished product
 * received into a goods warehouse, plus their reverse types.
 *
 * A document is a draft until posted; posting is what moves warehouse_stocks
 * and writes the stock_movements ledger. `erp_reference` / `erp_synced_at` are
 * filled by the ERP once it has booked its own counterpart document, so the
 * export endpoint can serve "not yet booked" documents only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();

            // Human/ERP-facing number, generated on create (see
            // StockDocumentService::nextDocumentNumber) or supplied by an import.
            $table->string('document_no', 60);

            // material_issue | material_receipt | product_receipt | product_issue
            // — see StockDocument::TYPES; the type fixes the sign of its lines.
            $table->string('type', 30);

            // draft | posted | cancelled — see StockDocument::STATUSES.
            $table->string('status', 20)->default('draft');

            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            // What the document was generated for. Both nullable: a manual
            // adjustment belongs to no work order.
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();

            $table->text('notes')->nullable();

            // ERP-side identity of this document, set when the ERP acknowledges it.
            $table->string('erp_reference', 100)->nullable();
            $table->timestamp('erp_synced_at')->nullable();

            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['type', 'status']);
            $table->index(['work_order_id']);
            $table->index(['erp_synced_at']);
        });

        // Partial unique so a number frees up after a soft delete; COALESCE
        // keeps single-tenant installs (tenant_id NULL) covered.
        DB::statement(
            'CREATE UNIQUE INDEX stock_documents_document_no_unique
             ON stock_documents (document_no, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_documents');
    }
};
