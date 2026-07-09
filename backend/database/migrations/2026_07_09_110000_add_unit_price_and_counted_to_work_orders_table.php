<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-order price (used to accrue a customer's total_revenue on completion) and
 * a one-shot guard so a work order only ever contributes to its customer's
 * order-count/revenue once — even if it is reopened and completed again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->nullable()->after('planned_qty')
                ->comment('Price per produced unit; drives customer total_revenue on completion');
            $table->boolean('customer_totals_counted')->default(false)->after('priority_score')
                ->comment('True once this order has been added to its customer totals');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'customer_totals_counted']);
        });
    }
};
