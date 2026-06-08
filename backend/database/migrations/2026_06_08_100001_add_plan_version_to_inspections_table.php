<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            // The plan version this inspection was performed against. The FK
            // already points at the exact plan-version row; this denormalized
            // number makes the version visible/auditable without a join and
            // survives even if the plan row is later removed.
            $table->unsignedInteger('plan_version')->nullable()->after('inspection_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn('plan_version');
        });
    }
};
