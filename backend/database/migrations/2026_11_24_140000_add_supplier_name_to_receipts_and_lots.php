<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->string('supplier_name', 150)->nullable()->after('warehouse_id');
        });
        Schema::table('material_lots', function (Blueprint $table) {
            $table->string('supplier_name', 150)->nullable()->after('material_id');
            $table->index(['tenant_id', 'supplier_name']);
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'supplier_name']);
            $table->dropColumn('supplier_name');
        });
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropColumn('supplier_name');
        });
    }
};
