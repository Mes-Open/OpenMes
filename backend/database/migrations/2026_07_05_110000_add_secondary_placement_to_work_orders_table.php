<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Independent schedule for the secondary-line placement. NULL means
            // "mirror the primary placement"; set on first independent drag.
            $table->timestamp('secondary_due_date')->nullable()->after('secondary_line_id');
            $table->smallInteger('secondary_shift_number')->nullable()->after('secondary_due_date');
            $table->date('secondary_end_date')->nullable()->after('secondary_shift_number');
            $table->smallInteger('secondary_end_shift_number')->nullable()->after('secondary_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'secondary_due_date',
                'secondary_shift_number',
                'secondary_end_date',
                'secondary_end_shift_number',
            ]);
        });
    }
};
