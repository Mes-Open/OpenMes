<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            // Material unit price captured at the moment of consumption, so the
            // cost report stays stable even if material.unit_price changes later.
            // NULL = legacy/unconsumed row — the report falls back to the live price.
            $table->decimal('unit_price_snapshot', 12, 4)->nullable()->after('consumed_at');
            $table->string('price_currency_snapshot', 3)->nullable()->after('unit_price_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            $table->dropColumn(['unit_price_snapshot', 'price_currency_snapshot']);
        });
    }
};
