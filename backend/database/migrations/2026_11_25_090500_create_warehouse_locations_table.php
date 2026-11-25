<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Physical warehouse locations: a two-level tree of zones and bins inside a
 * warehouse. A material lot records the bin it sits in; the existing
 * warehouse_stocks balance stays at warehouse level, so locations describe
 * where a lot is, not a second stock ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            // A zone is the top level; a bin always hangs under a zone. Nullable
            // so a zone can be the root without a synthetic parent row.
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

            $table->string('code', 50);
            $table->string('name');
            $table->string('kind', 20)->default('bin');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['warehouse_id', 'parent_id', 'sort_order']);
            $table->index(['warehouse_id', 'is_active']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX warehouse_locations_code_unique
             ON warehouse_locations (warehouse_id, code, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};
