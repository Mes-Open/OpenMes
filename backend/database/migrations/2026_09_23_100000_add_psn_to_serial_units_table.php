<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable, indexed process serial number column to serial units. The
 * process serial number links a unit to a process-level identifier and is
 * scanned again at packing to print the carton label. Not unique: a single
 * value may cover a batch of several units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serial_units', function (Blueprint $table) {
            $table->string('psn', 100)->nullable()->after('serial_no');
            $table->index('psn');
        });
    }

    public function down(): void
    {
        Schema::table('serial_units', function (Blueprint $table) {
            $table->dropIndex(['psn']);
            $table->dropColumn('psn');
        });
    }
};
