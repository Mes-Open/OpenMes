<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->string('source_container_no', 100)->nullable()->after('supplier_reference')
                ->comment('Scanned identifier of the physical source container (pallet/box/drum) the lot arrived in');
            // resolve() looks lots up by this exact value, so index it.
            $table->index('source_container_no');
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropIndex(['source_container_no']);
            $table->dropColumn('source_container_no');
        });
    }
};
