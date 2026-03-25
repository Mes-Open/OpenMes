<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->integer('max_cycles')->nullable(); // Max uses before maintenance
            $table->integer('current_cycles')->default(0);
            $table->float('wear_percentage')->default(0);
            $table->timestamp('last_maintenance_at')->nullable();
            $table->timestamp('decommissioned_at')->nullable();
            $table->jsonb('specs')->nullable(); // Industrial specs (RPM, Pressure, etc.)
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn(['max_cycles', 'current_cycles', 'wear_percentage', 'last_maintenance_at', 'decommissioned_at', 'specs']);
        });
    }
};
