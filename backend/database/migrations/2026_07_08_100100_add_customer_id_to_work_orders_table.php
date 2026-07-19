<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link work orders to a customer (nullable — existing orders and imports may
 * have no customer). Deleting a customer nulls the reference rather than
 * removing the work order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('customer_order_no')
                ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
