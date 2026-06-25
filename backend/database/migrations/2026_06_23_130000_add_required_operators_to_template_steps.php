<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operators a step needs while running. Drives the schedule-capacity crew axis,
 * which weights a line's labor demand by its orders' operator counts. Nullable
 * and defers to the linked process segment's `required_operators` (mirroring how
 * estimated_duration_minutes falls back), ultimately defaulting to 1 operator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('required_operators')->nullable()->after('estimated_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->dropColumn('required_operators');
        });
    }
};
