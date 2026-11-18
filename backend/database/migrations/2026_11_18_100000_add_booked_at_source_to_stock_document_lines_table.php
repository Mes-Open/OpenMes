<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A line of a production material release whose quantity the shop floor already
 * took off stock when it allocated and consumed the material. Posting such a line
 * confirms the paperwork without moving stock a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_document_lines', function (Blueprint $table) {
            $table->boolean('booked_at_source')->default(false)->after('unit_of_measure');
        });
    }

    public function down(): void
    {
        Schema::table('stock_document_lines', function (Blueprint $table) {
            $table->dropColumn('booked_at_source');
        });
    }
};
