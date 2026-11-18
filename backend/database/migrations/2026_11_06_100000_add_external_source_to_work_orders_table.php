<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an ERP-imported work order came from.
 *
 * The ERP import could not say which system and which order line an order
 * belongs to, who the customer is, or which currency `unit_price` is in, so
 * nothing produced for it could be matched back to the ERP's own documents.
 * `extra_data` (json) already exists and is reused for free-form ERP data.
 *
 * The (external_system, external_ref) index is deliberately not unique: an ERP
 * may reuse a reference, and the importer still matches orders by order_no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('external_system', 50)->nullable()->after('customer_order_no')
                ->comment('Source system that sent the order, e.g. the ERP name');
            $table->string('external_ref', 100)->nullable()->after('external_system')
                ->comment('Reference in the source system, e.g. a sales order line id');
            $table->string('customer_name', 255)->nullable()->after('external_ref')
                ->comment('Customer name as sent by the source system');
            $table->string('price_currency', 3)->nullable()->after('unit_price')
                ->comment('ISO 4217 code of unit_price; null = installation currency');

            $table->index(['external_system', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['external_system', 'external_ref']);
            $table->dropColumn(['external_system', 'external_ref', 'customer_name', 'price_currency']);
        });
    }
};
