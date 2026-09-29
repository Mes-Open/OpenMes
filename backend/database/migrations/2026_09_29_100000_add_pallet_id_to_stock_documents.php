<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A warehouse document can belong to one pallet: the finished-goods receipt
 * booked when it closes and the issue booked when it ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreignId('pallet_id')->nullable()->after('batch_id')->constrained('pallets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pallet_id');
        });
    }
};
