<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_plans', function (Blueprint $table) {
            // Version number within a version group. The first version is 1.
            $table->unsignedInteger('version')->default(1)->after('description');
            // NULL = draft (editable in place); set = published & immutable.
            $table->timestamp('published_at')->nullable()->after('version');
            // Version group: the first version is the root (root_id = NULL);
            // every later version points back to it. Lets us number versions
            // and list a plan's history regardless of name/scope changes.
            $table->foreignId('root_id')->nullable()->after('published_at')
                ->constrained('inspection_plans')->nullOnDelete();

            $table->index(['root_id', 'version']);
        });

        // Existing plans were already usable, so treat them as published at
        // their creation time (keeps them selectable for inspections; their
        // is_active flag still distinguishes live vs. retired).
        DB::table('inspection_plans')->whereNull('published_at')->update([
            'published_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('inspection_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('root_id');
            $table->dropColumn(['version', 'published_at']);
        });
    }
};
