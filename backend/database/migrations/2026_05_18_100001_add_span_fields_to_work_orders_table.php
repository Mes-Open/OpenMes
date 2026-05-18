<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('due_date');
            $table->unsignedTinyInteger('end_shift_number')->nullable()->after('shift_number');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'end_shift_number']);
        });
    }
};
