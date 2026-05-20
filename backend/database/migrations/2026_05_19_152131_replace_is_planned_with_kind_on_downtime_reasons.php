<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('downtime_reasons', function (Blueprint $table) {
            $table->string('kind', 20)->default('unplanned')->after('code');
        });

        DB::table('downtime_reasons')
            ->where('is_planned', true)
            ->update(['kind' => 'planned']);

        DB::table('downtime_reasons')
            ->where('code', 'changeover')
            ->update(['kind' => 'changeover']);

        Schema::table('downtime_reasons', function (Blueprint $table) {
            $table->dropColumn('is_planned');
        });
    }

    public function down(): void
    {
        Schema::table('downtime_reasons', function (Blueprint $table) {
            $table->boolean('is_planned')->default(false)->after('code');
        });

        DB::table('downtime_reasons')
            ->where('kind', 'planned')
            ->update(['is_planned' => true]);

        Schema::table('downtime_reasons', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
