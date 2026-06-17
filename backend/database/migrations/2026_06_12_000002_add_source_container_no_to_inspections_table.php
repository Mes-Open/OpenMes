<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('source_container_no', 100)->nullable()->after('supplier_lot_ref')
                ->comment('Scanned at receiving; copied onto the material lot the inspection creates');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn('source_container_no');
        });
    }
};
