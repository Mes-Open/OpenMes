<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            // Per-worker compensation, authoritative over the wage_group preset.
            // pay_type: hourly | weekly | piece_rate (validated via Worker::PAY_TYPES).
            // pay_rate meaning depends on pay_type: amount per hour / per week / per piece.
            $table->string('pay_type', 20)->nullable()->after('wage_group_id');
            $table->decimal('pay_rate', 12, 4)->nullable()->after('pay_type');
            $table->string('pay_currency', 3)->nullable()->after('pay_rate');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['pay_type', 'pay_rate', 'pay_currency']);
        });
    }
};
