<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISA-95 Material Lot Tracking — physical lots received against Material master data.
 *
 * ISA-95 hierarchy: Material Class → Material Definition (materials) →
 * Material Lot (this table) → Material Sublot (next migration).
 *
 * A lot is a physically distinguishable quantity of a material that arrived
 * together (one delivery / supplier lot reference), has a single status, and
 * tracks its remaining available quantity as production consumes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lots', function (Blueprint $table) {
            $table->id();

            // User-facing identifier — e.g. BOLT-M10-2026-W21-001.
            // Generated externally (LotSequence) or entered manually on receipt.
            $table->string('lot_number', 100);

            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('material_sources')->nullOnDelete();

            // Quantity tracking — decimal(14,4) supports both kg/L (fractional) and pcs (whole).
            $table->decimal('quantity_received', 14, 4);
            $table->decimal('quantity_available', 14, 4);
            $table->string('unit_of_measure', 20);

            // Lifecycle dates.
            $table->timestamp('received_at');
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();

            // ISA-95 lot status — stored as VARCHAR for Postgres/SQLite portability.
            //   received     pending QC after physical arrival
            //   quarantine   failed inspection, on hold
            //   released     passed QC, available for consumption
            //   consumed     quantity_available reached zero
            //   expired      past expiry_date
            //   rejected     permanently rejected (return-to-supplier / scrap)
            $table->string('status', 20)->default('received');

            // Optional supplier traceability.
            $table->string('supplier_lot_no', 100)->nullable();
            $table->string('supplier_reference', 255)->nullable();

            // Link back to the inbound inspection that decided this lot's fate.
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();

            // Audit / multi-tenancy.
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('extra_data')->nullable();
            $table->timestamps();

            // Lot numbers are unique within a tenant — different tenants may reuse the same code.
            $table->unique(['lot_number', 'tenant_id']);
            $table->index(['material_id', 'status']);
            $table->index(['expiry_date', 'status']);
            $table->index(['received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lots');
    }
};
