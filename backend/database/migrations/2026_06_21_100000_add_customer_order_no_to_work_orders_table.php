<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer's own order/PO reference for a work order. Free-text, nullable,
 * non-unique (several work orders can belong to one customer order) and indexed
 * so the traceability console can search by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('customer_order_no', 100)->nullable()->after('order_no');
            $table->index('customer_order_no');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['customer_order_no']);
            $table->dropColumn('customer_order_no');
        });
    }
};
