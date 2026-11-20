<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scan-to-load verification (#400): a shipment (one truck / load), the units it
 * is expected to carry, and the append-only log of every scan at the dock.
 *
 * All three are soft-deletable with an audit column. Uniqueness is partial (live
 * rows only) so a load ID or a label can be used again after a delete; COALESCE
 * keeps single-tenant installs (tenant_id NULL) covered by the shipment_no index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            // External load ID — the number the ERP / carrier knows the truck by.
            $table->string('shipment_no', 64);
            $table->string('erp_reference', 100)->nullable();
            $table->string('carrier', 255)->nullable();
            $table->string('vehicle_no', 64)->nullable();
            $table->string('destination', 255)->nullable();
            $table->timestamp('planned_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('planned');

            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            // Why a supervisor let the truck go with units missing.
            $table->text('short_reason')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['status']);
            $table->index(['updated_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX shipments_shipment_no_unique
             ON shipments (shipment_no, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL'
        );

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            // pallet | carton
            $table->string('unit_type', 10);
            // The label number as the expected list names it.
            $table->string('identifier', 64);
            // Resolved when the label is known in OpenMES (at import or at scan).
            $table->foreignId('pallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('carton_id')->nullable()->constrained('unit_cartons')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();
            $table->foreignId('loaded_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['pallet_id']);
            $table->index(['carton_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX shipment_items_unique
             ON shipment_items (shipment_id, unit_type, identifier)
             WHERE deleted_at IS NULL'
        );

        Schema::create('shipment_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('scanned_code', 128);
            $table->string('unit_type', 10)->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreignId('shipment_item_id')->nullable()->constrained()->nullOnDelete();
            // loaded | duplicate | unexpected | unknown | blocked | removed
            $table->string('result', 20);
            $table->string('message', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('scanned_at');

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['shipment_id', 'scanned_at']);
            $table->index(['scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_scans');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
