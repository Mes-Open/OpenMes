<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-step throughput counter: how many units have passed / left this station,
 * incremented by a break-beam sensor pulse via the MQTT `count_step` action.
 * Distinct from the work order's produced_qty (finished goods) — an intermediate
 * station tracks throughput without inflating the finished count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->unsignedInteger('passed_qty')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropColumn('passed_qty');
        });
    }
};
