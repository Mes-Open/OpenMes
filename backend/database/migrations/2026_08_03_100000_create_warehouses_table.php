<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouses (#212) — named stock locations material is issued from and
 * finished product is received into. `kind` decides what a warehouse may hold
 * (raw material, finished goods, or both), and `erp_code` carries the
 * counterpart identifier in the ERP so stock documents can be matched up on
 * both sides of a sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();

            // raw_material | finished_goods | mixed — see Warehouse::KINDS.
            $table->string('kind', 20)->default('mixed');

            // Identifier of this warehouse in the connected ERP (Pantheon
            // acWarehouse, SAP plant/storage location, …). Nullable: an
            // OpenMES-only warehouse never syncs.
            $table->string('erp_code', 100)->nullable();

            // Fallback used when an import or an auto-generated document does
            // not name a warehouse. At most one default per kind (below).
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['kind', 'is_active']);
        });

        // Partial uniques so a code / ERP code frees up again after a soft
        // delete. COALESCE keeps single-tenant installs (tenant_id NULL)
        // covered — a plain unique would treat every NULL tenant as distinct.
        DB::statement(
            'CREATE UNIQUE INDEX warehouses_code_unique
             ON warehouses (code, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX warehouses_erp_code_unique
             ON warehouses (erp_code, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL AND erp_code IS NOT NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX warehouses_default_per_kind_unique
             ON warehouses (kind, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL AND is_default = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
