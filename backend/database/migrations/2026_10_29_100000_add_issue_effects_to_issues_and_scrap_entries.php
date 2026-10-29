<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What an issue does to production once it is filed.
 *
 * Until now an issue blocked its work order only through its type's
 * `is_blocking` flag, read live — so the person filing it could not decide, and
 * flipping the flag on a type silently re-classified every issue ever filed
 * under it. Supervisors filing an issue from the Issues page now choose its
 * effect (block the work order, or record scrap), so the decision is stored on
 * the issue itself: `blocks_work_order` is copied from the type when nothing
 * else is said, and existing rows are backfilled from their type here.
 *
 * `scrap_entries.issue_id` ties a scrap entry recorded together with an issue
 * back to it, so the quantity is traceable to the problem that caused it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->boolean('blocks_work_order')
                ->default(false)
                ->after('issue_type_id')
                ->comment('Whether this issue holds its work order in BLOCKED until resolved');
        });

        DB::table('issues')
            ->whereIn('issue_type_id', DB::table('issue_types')->where('is_blocking', true)->select('id'))
            ->update(['blocks_work_order' => true]);

        Schema::table('scrap_entries', function (Blueprint $table) {
            $table->foreignId('issue_id')
                ->nullable()
                ->after('work_order_id')
                ->constrained('issues')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scrap_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issue_id');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('blocks_work_order');
        });
    }
};
