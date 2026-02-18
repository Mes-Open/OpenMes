<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('week_number')->nullable()->after('due_date');
            $table->unsignedTinyInteger('month_number')->nullable()->after('week_number');
            $table->unsignedSmallInteger('production_year')->nullable()->after('month_number');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['week_number', 'month_number', 'production_year']);
        });
    }
};
