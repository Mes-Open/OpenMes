<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->float('maintenance_threshold')->default(80.0); // 0-100 threshold
            $table->float('failure_probability')->default(0.0);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->float('maintenance_threshold')->default(85.0);
            $table->float('failure_probability')->default(0.0);
        });
    }

    public function down(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn(['maintenance_threshold', 'failure_probability']);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn(['maintenance_threshold', 'failure_probability']);
        });
    }
};
