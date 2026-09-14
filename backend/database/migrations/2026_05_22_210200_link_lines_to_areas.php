<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            // Constrained only when the table it points at exists. These lookups
            // now ship as an optional module, so a community installation never
            // creates them — the column stays, unconstrained, holding nothing.
            $column = $table->foreignId('area_id')->nullable()->after('id');
            if (Schema::hasTable('areas')) {
                $column->constrained()->nullOnDelete();
            }
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dropIndex(['area_id']);
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
