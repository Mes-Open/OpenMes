<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // A work order can run on a second production line in the same
            // time window; scheduling fields (due date, shifts, minutes) are
            // shared between both placements.
            $table->foreignId('secondary_line_id')
                ->nullable()
                ->after('line_id')
                ->constrained('lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secondary_line_id');
        });
    }
};
