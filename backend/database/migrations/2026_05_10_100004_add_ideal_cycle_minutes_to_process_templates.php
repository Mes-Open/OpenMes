<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_templates', function (Blueprint $table) {
            $table->decimal('ideal_cycle_minutes', 8, 4)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('process_templates', function (Blueprint $table) {
            $table->dropColumn('ideal_cycle_minutes');
        });
    }
};
