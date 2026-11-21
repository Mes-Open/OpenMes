<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator panel presence on the Workstation devices roster.
 *
 * Besides the OpenMES Workstation client, the browser-based operator panel now
 * heartbeats too: it reports who is logged in (user_id) and whether the panel
 * is active (status), plus the workstation the operator selected
 * (workstation_id). Existing client rows default to "active" so their online
 * state keeps being driven by last_seen_at alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstation_devices', function (Blueprint $table) {
            // The user logged in to the panel on this device (last one seen).
            $table->foreignId('user_id')->nullable()->after('line_id')->constrained('users')->nullOnDelete();
            // What the device last reported (App\Enums\WorkstationDeviceStatus).
            $table->string('status', 16)->default('active')->after('user_id');
            // Workstation selected in the operator panel (line_id holds its line).
            $table->foreignId('workstation_id')->nullable()->after('line_id')->constrained('workstations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workstation_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workstation_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('status');
        });
    }
};
