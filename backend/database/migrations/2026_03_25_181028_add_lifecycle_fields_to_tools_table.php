<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            if (!Schema::hasColumn('tools', 'max_cycles')) {
                $table->integer('max_cycles')->nullable();
            }
            if (!Schema::hasColumn('tools', 'current_cycles')) {
                $table->integer('current_cycles')->default(0);
            }
            if (!Schema::hasColumn('tools', 'wear_percentage')) {
                $table->float('wear_percentage')->default(0);
            }
            if (!Schema::hasColumn('tools', 'last_maintenance_at')) {
                $table->timestamp('last_maintenance_at')->nullable();
            }
            if (!Schema::hasColumn('tools', 'decommissioned_at')) {
                $table->timestamp('decommissioned_at')->nullable();
            }
            if (!Schema::hasColumn('tools', 'specs')) {
                $table->jsonb('specs')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn(['max_cycles', 'current_cycles', 'wear_percentage', 'last_maintenance_at', 'decommissioned_at', 'specs']);
        });
    }
};
