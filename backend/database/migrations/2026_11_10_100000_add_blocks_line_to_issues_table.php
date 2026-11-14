<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An issue filed from the Issues page can stop a whole line (or one machine on
 * it) rather than only its own work order: it opens a stop on the line and
 * blocks every running order there until the issue is resolved.
 *
 * The stop itself is the issue's `production_downtime_id`; this flag marks the
 * issue as the one holding the line, so resolving it knows to end the stop and
 * release the line's orders, and so a second line-blocking issue on the same
 * line keeps them blocked until it is resolved too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->boolean('blocks_line')
                ->default(false)
                ->after('blocks_work_order')
                ->comment('Whether this issue holds its line (and the work orders on it) stopped until resolved');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('blocks_line');
        });
    }
};
