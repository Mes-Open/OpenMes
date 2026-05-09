<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('stock_quantity', 12, 3)->default(0)->after('default_scrap_percentage');
            $table->decimal('min_stock_level', 12, 3)->nullable()->after('stock_quantity');
            $table->string('supplier_name', 255)->nullable()->after('external_system');
            $table->string('supplier_code', 100)->nullable()->after('supplier_name');
            $table->decimal('unit_price', 12, 4)->nullable()->after('supplier_code');
            $table->string('price_currency', 3)->default('PLN')->after('unit_price');
            $table->string('ean', 20)->nullable()->after('price_currency');
            $table->timestamp('last_stock_sync_at')->nullable()->after('ean');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn([
                'stock_quantity', 'min_stock_level',
                'supplier_name', 'supplier_code',
                'unit_price', 'price_currency',
                'ean', 'last_stock_sync_at',
            ]);
        });
    }
};
