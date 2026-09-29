<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of work a routing step is. Every step used to be "production";
 * packing is a step of its own kind, carrying its configuration (what goes
 * into what, how many, which label) so the packing station knows what to do
 * for this product instead of one global behaviour. Copied onto batch steps
 * with the rest of the frozen snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['template_steps', 'batch_steps'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('kind', 20)->default('production')->after('name');
                $t->json('config')->nullable()->after('kind');
                $t->index('kind');
            });
        }
    }

    public function down(): void
    {
        foreach (['template_steps', 'batch_steps'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['kind']);
                $t->dropColumn(['kind', 'config']);
            });
        }
    }
};
