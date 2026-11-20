<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shelf life of a product (e.g. 24 months). A batch of the product gets its
     * expiry date from it when the batch is created: creation day + shelf life.
     */
    public function up(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->unsignedInteger('shelf_life_value')->nullable()->after('unit_of_measure');
            $table->string('shelf_life_unit', 10)->nullable()->after('shelf_life_value'); // days | months
        });
    }

    public function down(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_value', 'shelf_life_unit']);
        });
    }
};
