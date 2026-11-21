<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a configuration version was appended when no change request did it —
     * e.g. `process_template_edited` when a not-started order is moved onto the
     * edited process template. Null for versions written by a change request
     * (its own row says why) and for the release baseline.
     */
    public function up(): void
    {
        Schema::table('work_order_snapshots', function (Blueprint $table) {
            $table->string('reason', 50)->nullable()
                ->comment('Why this version was appended without a change request, e.g. process_template_edited');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_snapshots', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
