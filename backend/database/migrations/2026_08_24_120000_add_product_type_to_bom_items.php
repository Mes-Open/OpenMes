<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A BOM line can now be a manufactured **product type** (a sub-assembly), not
 * only a material. Exactly one of `material_id` / `product_type_id` is set per
 * row (enforced in the Form-request/service layer). Product-type lines are a
 * simple component reference — they do not explode into their own BOM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            // Was NOT NULL — a product-type line carries no material.
            $table->unsignedBigInteger('material_id')->nullable()->change();

            $table->foreignId('product_type_id')->nullable()->after('material_id')
                ->constrained('product_types')->restrictOnDelete();
        });

        // Partial unique (soft-delete aware, same rule as the material index): a
        // given product type may appear once in a template's live BOM. Postgres
        // and SQLite both honour the WHERE clause.
        DB::statement(
            'CREATE UNIQUE INDEX bom_items_template_product_type_unique '.
            'ON bom_items (process_template_id, product_type_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bom_items_template_product_type_unique');

        Schema::table('bom_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_type_id');
        });

        Schema::table('bom_items', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable(false)->change();
        });
    }
};
