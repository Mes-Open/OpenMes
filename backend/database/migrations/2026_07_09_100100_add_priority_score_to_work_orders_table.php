<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw summed score from the priority rules (may be negative). The existing
 * `priority` column holds the 1–5 band this score maps to; the planner ranks by
 * this finer-grained score. Defaults to 0 and stays untouched until at least one
 * active priority rule exists (scoring is opt-in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->integer('priority_score')->default(0)->after('priority')
                ->comment('Summed points from priority rules; maps to the 1–5 priority');
            $table->index('priority_score');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['priority_score']);
            $table->dropColumn('priority_score');
        });
    }
};
