<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP identity + classification on product types (#212).
 *
 * `category` is the ERP's own grouping of an item (Pantheon calls it a
 * Classification, acClassif) — carried so an ERP product import can be limited
 * to the categories that actually belong in production, and so the category is
 * visible in OpenMES afterwards. external_code / external_system mirror the
 * columns materials already have, so both master-data tables identify their
 * source the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('description')
                ->comment('ERP classification / item group this product belongs to');
            $table->string('external_code', 100)->nullable()->after('category')
                ->comment('Identifier of this product in the source ERP');
            $table->string('external_system', 50)->nullable()->after('external_code')
                ->comment('Name of the ERP the product was imported from');

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'external_code', 'external_system']);
        });
    }
};
