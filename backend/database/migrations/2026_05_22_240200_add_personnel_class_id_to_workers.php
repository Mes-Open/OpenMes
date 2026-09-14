<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link Worker → Personnel Class (nullable for backward compatibility).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            // Constrained only when the table it points at exists. These lookups
            // now ship as an optional module, so a community installation never
            // creates them — the column stays, unconstrained, holding nothing.
            $column = $table->foreignId('personnel_class_id')->nullable()->after('id');
            if (Schema::hasTable('personnel_classes')) {
                $column->constrained()->nullOnDelete();
            }
            $table->index('personnel_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropIndex(['personnel_class_id']);
            $table->dropForeign(['personnel_class_id']);
            $table->dropColumn('personnel_class_id');
        });
    }
};
