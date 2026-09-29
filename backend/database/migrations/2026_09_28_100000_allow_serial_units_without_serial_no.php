<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A unit can start life with only its process serial (PSN): the line numbers
 * the unit at its first station, binds components to that number, and the
 * final serial number (SN) arrives later with the product label. The unique
 * (serial_no, tenant_id) index stays: it allows any number of units without an
 * SN yet, and still one unit per SN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serial_units', function (Blueprint $table) {
            $table->string('serial_no', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Units still waiting for their SN get a placeholder built from their PSN
        // and id: the PSN alone may be missing or shared, the serial is unique.
        \Illuminate\Support\Facades\DB::table('serial_units')->whereNull('serial_no')->update(['serial_no' => \Illuminate\Support\Facades\DB::raw("COALESCE(psn, 'UNIT') || '-' || id")]);
        Schema::table('serial_units', function (Blueprint $table) {
            $table->string('serial_no', 100)->nullable(false)->change();
        });
    }
};
