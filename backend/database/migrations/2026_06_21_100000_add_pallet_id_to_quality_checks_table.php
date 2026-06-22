<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a quality result (QualityCheck) to the output pallet it relates to (#106),
 * so quality status can be tracked at pallet level. Nullable — a check may not
 * concern a specific pallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_checks', function (Blueprint $table) {
            $table->foreignId('pallet_id')->nullable()->after('batch_id')->constrained()->nullOnDelete();
            $table->index('pallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('quality_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pallet_id');
        });
    }
};
