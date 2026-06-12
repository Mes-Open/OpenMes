<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packaging_scan_logs', function (Blueprint $table) {
            $table->foreignId('pallet_id')
                ->nullable()
                ->after('work_order_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packaging_scan_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pallet_id');
        });
    }
};
