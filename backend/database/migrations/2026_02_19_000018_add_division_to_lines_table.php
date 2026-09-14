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
            $column = $table->foreignId('division_id')->nullable()->after('id');
            if (Schema::hasTable('divisions')) {
                $column->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn('division_id');
        });
    }
};
