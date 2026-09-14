<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Unit-mode piece can now start work before it has a serial number (#290
 * known-bugs item 3) — on a real line the serial is sometimes only assigned
 * a few steps in (a nameplate applied later, a scan at a downstream
 * station), not always known at registration. NULL means "not serialized
 * yet"; UnitProgressionService::assignSerial() fills it in later.
 *
 * The existing unique index (serial_no, tenant_id) needs no change: Postgres
 * treats each NULL as distinct, so any number of not-yet-serialized pieces
 * can coexist for the same tenant without violating uniqueness.
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
        if (DB::table('serial_units')->whereNull('serial_no')->exists()) {
            throw new \RuntimeException(
                'Cannot roll back: some serial_units have a NULL serial_no. '
                .'Assign a serial number to them (or delete those rows) before rolling back.'
            );
        }

        Schema::table('serial_units', function (Blueprint $table) {
            $table->string('serial_no', 100)->nullable(false)->change();
        });
    }
};
