<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            // Flags a worker as a logistics operator / forklift driver — the
            // people eligible to perform physical pallet movements. Drives the
            // operator picker on the shop-floor Move Pallet terminal (#103).
            $table->boolean('is_logistics')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('is_logistics');
        });
    }
};
