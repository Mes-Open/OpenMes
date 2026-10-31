<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a work order goes back to when its last blocking issue is resolved.
 *
 * Unblocking used to guess: IN_PROGRESS from the Issues page, or IN_PROGRESS /
 * PENDING from whether a batch was running. A PENDING or ACCEPTED order blocked
 * before anyone started it came back as started — and an order with a future
 * planned start could not be saved at all. The status the block replaced is now
 * remembered here and restored, then cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('status_before_block', 20)
                ->nullable()
                ->after('status')
                ->comment('Status a blocking issue replaced; restored when the order is unblocked');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('status_before_block');
        });
    }
};
