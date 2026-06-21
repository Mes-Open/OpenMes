<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalized pallet quality status (#106): pending | pass | fail, derived from
 * the quality checks linked to the pallet and recomputed whenever one changes.
 * Kept on the row so the live `pallets` shape can show and filter by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->string('quality_status', 10)->default('pending')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn('quality_status');
        });
    }
};
