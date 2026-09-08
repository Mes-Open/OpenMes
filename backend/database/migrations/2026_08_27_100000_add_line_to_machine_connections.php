<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assign a machine/MQTT device to a production line ("this machine feeds line X").
 * Used as the default target for its topic mappings when a mapping doesn't name
 * a line itself — so a break-beam sensor's pulses land on the line's running
 * work order without per-order configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_connections', function (Blueprint $table) {
            $table->foreignId('line_id')->nullable()->after('protocol')
                ->constrained('lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('machine_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_id');
        });
    }
};
